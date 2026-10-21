<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\DocumentVersion;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Criterios de aceptación de los requerimientos RF-AUT, USR, EXP, DOC, PRM, VIS, BUS, AUD y VER. */
class RequirementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function account(string $role = 'READER', array $data = []): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true, ...$data]);
    }

    private function expediente(User $owner, array $data = []): Expediente
    {
        return Expediente::create(['name' => 'Expediente '.Str::random(5), 'responsible_id' => $owner->id, ...$data]);
    }

    private function version(Expediente $e, int $number = 1, string $mime = 'application/pdf', ?string $name = null): DocumentVersion
    {
        $doc = $e->documents()->firstOrCreate(['name' => $name ?? 'INE'], ['type' => 'INE']);
        $path = 'documents/'.Str::random(10).'.pdf';
        Storage::disk('local')->put($path, "%PDF-1.4 contenido $number");

        return $doc->versions()->create(['version' => $number, 'path' => $path, 'original_name' => ($name ?? 'INE').($mime === 'application/pdf' ? '.pdf' : '.docx'), 'mime' => $mime, 'size' => 20, 'hash' => hash('sha256', $path), 'uploaded_by' => $e->responsible_id]);
    }

    private function pdf(string $name, string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n$content");
    }

    // RF-AUT-001 / RF-AUT-002
    public function test_login_and_logout_are_audited_with_generic_failure_message(): void
    {
        $user = $this->account();
        $this->post('/login', ['username' => $user->username, 'password' => 'incorrecta'])->assertSessionHasErrors(['username' => 'Credenciales incorrectas o cuenta desactivada.']);
        $this->post('/login', ['username' => 'no.existe', 'password' => 'password'])->assertSessionHasErrors(['username' => 'Credenciales incorrectas o cuenta desactivada.']);
        $inactive = $this->account('READER', ['is_active' => false]);
        $this->post('/login', ['username' => $inactive->username, 'password' => 'password'])->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->post('/login', ['username' => $user->username, 'password' => 'password'])->assertRedirect(route('expedientes.index'));
        $this->post('/logout');

        $this->assertSame(3, AuditEvent::where('action', 'auth.login')->where('result', 'failure')->count());
        $this->assertDatabaseHas('audit_events', ['action' => 'auth.login', 'result' => 'success', 'user_id' => $user->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'auth.logout', 'user_id' => $user->id]);
        $this->assertStringNotContainsString('incorrecta', AuditEvent::all()->toJson());
    }

    // RF-AUT-003 CA-02, RF-PRM-001
    public function test_loader_cannot_assign_read_permissions_and_denial_is_audited(): void
    {
        $loader = $this->account('LOADER');
        $e = $this->expediente($loader);
        $this->actingAs($loader)->post(route('grants.store', $e), ['user_id' => $this->account()->id, 'starts_at' => now()->toDateTimeString()])->assertForbidden();
        $this->assertSame(0, $e->grants()->count());
        $this->assertDatabaseHas('audit_events', ['action' => 'access.denied', 'result' => 'denied', 'user_id' => $loader->id, 'expediente_id' => $e->id]);
    }

    // RF-AUT-004
    public function test_session_cookie_is_http_only_same_site_and_deactivation_ends_access(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
        $admin = $this->account('ADMIN');
        $reader = $this->account();
        $this->actingAs($admin)->put(route('users.update', $reader), ['name' => $reader->name, 'username' => $reader->username, 'role' => 'READER', 'is_active' => 0])->assertSessionHasNoErrors();
        $this->actingAs($reader->fresh())->get(route('expedientes.index'))->assertRedirect('/login');
        $this->assertDatabaseHas('audit_events', ['action' => 'user.deactivated', 'resource_id' => $reader->id]);
    }

    public function test_removed_password_change_page_and_endpoint_are_not_available(): void
    {
        $user = $this->account();
        $this->actingAs($user)->get('/cuenta/contrasena')->assertNotFound();
        $this->put('/cuenta/contrasena')->assertNotFound();
    }

    // RF-USR-001 / 002 / 004
    public function test_user_registration_and_role_changes_are_validated_and_audited(): void
    {
        $admin = $this->account('ADMIN');
        $existing = $this->account('LOADER', ['username' => 'maria.lopez']);
        $this->actingAs($admin)->post(route('users.store'), ['name' => 'Otra', 'username' => 'Maria.Lopez', 'role' => 'READER', 'is_active' => 1, 'password' => 'Password123456', 'password_confirmation' => 'Password123456'])->assertSessionHasErrors('username');
        $this->post(route('users.store'), ['name' => 'Otra', 'username' => 'otra', 'role' => 'SUPERVISOR', 'is_active' => 1, 'password' => 'Password123456', 'password_confirmation' => 'Password123456'])->assertSessionHasErrors('role');
        $this->post(route('users.store'), ['name' => 'Ana Ruiz', 'username' => 'ana.ruiz', 'role' => 'READER', 'is_active' => 1, 'password' => 'Password123456', 'password_confirmation' => 'Password123456'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_events', ['action' => 'user.created', 'user_id' => $admin->id]);

        $this->put(route('users.update', $existing), ['name' => 'María López', 'username' => 'maria.lopez', 'role' => 'READER', 'is_active' => 1])->assertSessionHasNoErrors();
        $event = AuditEvent::where('action', 'user.role_changed')->firstOrFail();
        $this->assertSame(['from' => 'LOADER', 'to' => 'READER'], $event->metadata);
        $this->assertSame($admin->id, $event->user_id);
        $this->assertDatabaseHas('audit_events', ['action' => 'user.updated', 'resource_id' => $existing->id]);

        // CA-03 RF-USR-004: el usuario degradado pierde de inmediato las capacidades de LOADER.
        $this->actingAs($existing->fresh())->post(route('expedientes.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($this->account('LOADER'))->put(route('users.update', $existing), ['name' => 'X', 'username' => 'maria.lopez', 'role' => 'ADMIN', 'is_active' => 1])->assertForbidden();
    }

    // RF-USR-005
    public function test_users_can_be_searched_and_paginated_in_fixed_pages_of_ten(): void
    {
        $admin = $this->account('ADMIN', ['name' => 'Admin Central']);
        $this->account('LOADER', ['name' => 'Carla Cargadora', 'username' => 'carla']);
        $this->account('READER', ['name' => 'Luis Lector', 'username' => 'luis', 'is_active' => false]);
        User::factory()->count(12)->create(['role' => 'READER']);
        $this->actingAs($admin)->get(route('users.index', ['q' => 'carla']))->assertSee('Carla Cargadora')->assertDontSee('Luis Lector');
        $this->get(route('users.index', ['q' => 'luis']))->assertSee('Luis Lector')->assertDontSee('Carla Cargadora');
        $this->get(route('users.index', ['per_page' => 25, 'role' => 'READER', 'status' => 'inactive']))
            ->assertOk()->assertSee('1–10 de 15')->assertSee('Página 1 de 2')
            ->assertSee('data-listado-dinamico', false)->assertSee('data-limpiar-filtros', false)
            ->assertDontSee('name="role"', false)->assertDontSee('name="status"', false)->assertDontSee('name="per_page"', false)
            ->assertDontSee('Aplicar filtros')
            ->assertViewHas('users', fn ($users) => $users->perPage() === 10 && $users->count() === 10 && $users->total() === 15);
        $this->get(route('users.index', ['page' => 2]))->assertOk()->assertSee('11–15 de 15')
            ->assertViewHas('users', fn ($users) => $users->count() === 5);
        $this->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('users.index', ['q' => 'carla']))->assertOk()
            ->assertSee('data-listado-resultados', false)->assertSee('1 cuenta')->assertSee('Carla Cargadora')->assertDontSee('Luis Lector');
    }

    // RF-EXP-001 / RF-EXP-002
    public function test_only_loader_creates_expedientes_and_admin_cannot_edit(): void
    {
        $admin = $this->account('ADMIN');
        $this->actingAs($admin)->post(route('expedientes.store'), ['name' => 'X'])->assertForbidden();
        $loader = $this->account('LOADER');
        $this->actingAs($loader)->from(route('expedientes.create'))->post(route('expedientes.store'), ['description' => 'Sin título'])
            ->assertRedirect(route('expedientes.create'))->assertSessionHasErrors('name')->assertSessionHasInput('description', 'Sin título');
        $response = $this->post(route('expedientes.store'), ['name' => 'Ingreso 2026'])->assertSessionHasNoErrors();
        $e = Expediente::firstOrFail();
        $response->assertRedirect(route('expedientes.show', $e));
        $this->assertSame($loader->id, $e->responsible_id);
        $this->assertSame('EXP-'.now()->format('Y').'-000001', $e->folio);
        $this->post(route('expedientes.store'), ['name' => 'Ingreso 2026 bis'])->assertSessionHasNoErrors();
        $this->assertSame('EXP-'.now()->format('Y').'-000002', Expediente::latest('id')->first()->folio);
        $this->get(route('expedientes.show', $e))->assertOk();
        $this->put(route('expedientes.update', $e), ['name' => 'Ingreso 2026 (actualizado)', 'folio' => 'otro', 'id' => 999])->assertSessionHasNoErrors();
        $this->assertSame($e->folio, $e->fresh()->folio);
        $this->assertDatabaseHas('audit_events', ['action' => 'expediente.updated', 'expediente_id' => $e->id]);
        $this->actingAs($admin)->put(route('expedientes.update', $e), ['name' => 'Admin'])->assertForbidden();
        $this->post(route('documents.store', $e), ['type' => 'INE', 'files' => [$this->pdf('a.pdf', 'a')]])->assertForbidden();
        $this->actingAs($admin)->get(route('expedientes.show', $e))->assertOk()->assertDontSee('Archivar')->assertDontSee('Historial de estados');
        // El archivado y el estado del expediente se retiraron del alcance.
        $this->assertFalse(Route::has('expedientes.status'));
        $this->assertFalse(Schema::hasColumn('expedientes', 'status'));
    }

    // RF-DOC-001 / RF-DOC-002
    public function test_multiple_files_upload_atomically_with_catalog_type(): void
    {
        $loader = $this->account('LOADER');
        $e = $this->expediente($loader);
        $this->actingAs($loader)->post(route('documents.store', $e), ['type' => 'Inventado', 'files' => [$this->pdf('a.pdf', 'a')]])->assertSessionHasErrors('type');
        $this->post(route('documents.store', $e), ['type' => 'INE', 'files' => [$this->pdf('a.pdf', 'a'), UploadedFile::fake()->createWithContent('b.pdf', 'no es pdf')]])->assertSessionHasErrors('files');
        $this->assertSame(0, $e->documents()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->post(route('documents.store', $e), ['type' => 'CURP', 'description' => 'Lote de ingreso', 'files' => [$this->pdf('curp.pdf', 'a'), $this->pdf('curp-reverso.pdf', 'b')]])->assertSessionHasNoErrors();
        $this->assertSame(['curp', 'curp-reverso'], $e->documents()->orderBy('name')->pluck('name')->all());
        $this->assertSame(2, $e->documents()->where('type', 'CURP')->where('description', 'Lote de ingreso')->count());
        $this->assertSame(2, AuditEvent::where('action', 'document.uploaded')->count());
        $this->post(route('documents.store', $e), ['type' => 'INE', 'files' => [UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf')]])->assertSessionHasErrors('files.0');
    }

    // RF-DOC-004
    public function test_document_retirement_is_logical_with_reason(): void
    {
        $loader = $this->account('LOADER');
        $e = $this->expediente($loader);
        $v = $this->version($e);
        $reader = $this->account();
        $e->grants()->create(['user_id' => $reader->id, 'granted_by' => $loader->id, 'starts_at' => now()->subMinute(), 'can_download' => true]);
        $this->actingAs($loader)->post(route('documents.retire', $v->document), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('documents.retire', $v->document), ['reason' => 'Documento ilegible'])->assertSessionHasNoErrors();
        $doc = $v->document()->first();
        $this->assertTrue($doc->trashed());
        $this->assertSame($loader->id, $doc->deleted_by);
        $this->assertSame('Documento ilegible', $doc->deletion_reason);
        $this->assertSame(1, $doc->versions()->count());
        $this->get(route('expedientes.show', $e))->assertSee('Documentos dados de baja (1)')->assertSee('Documento ilegible');
        $this->actingAs($reader)->get(route('expedientes.show', $e))->assertDontSee('INE.pdf');
        $this->get(route('documents.file', $v))->assertForbidden();
        $this->assertDatabaseHas('audit_events', ['action' => 'document.retired', 'resource_id' => $doc->id]);
    }

    // RF-PRM-003 / RF-PRM-004
    public function test_permissions_report_grantor_and_download_is_hidden_when_forbidden(): void
    {
        $admin = $this->account('ADMIN', ['name' => 'Admin Otorgante']);
        $loader = $this->account('LOADER');
        $e = $this->expediente($loader);
        $v = $this->version($e);
        $reader = $this->account();
        $this->actingAs($admin)->post(route('grants.store', $e), ['user_id' => $reader->id, 'starts_at' => now()->subMinute()->toDateTimeString()]);
        $this->get(route('users.edit', $reader))->assertSee($e->name)->assertSee('Admin Otorgante');
        $this->get(route('expedientes.show', $e))->assertSee('Admin Otorgante');
        $this->actingAs($reader)->get(route('documents.viewer', $v))->assertOk()->assertDontSee('Descargar');
        $this->get(route('documents.file', ['version' => $v, 'download' => 1]))->assertForbidden();
        $this->assertDatabaseHas('audit_events', ['action' => 'access.denied', 'user_id' => $reader->id, 'resource_id' => $v->id]);
    }

    // RF-VIS-001 / RF-VIS-002
    public function test_viewer_shows_pdf_and_informs_formats_without_viewer_and_audits(): void
    {
        $loader = $this->account('LOADER');
        $e = $this->expediente($loader);
        $pdf = $this->version($e);
        $docx = $this->version($e, 1, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Contrato');
        $this->actingAs($loader)->get(route('documents.viewer', $pdf))->assertOk()->assertSee('<iframe', false);
        $this->get(route('documents.viewer', $docx))->assertOk()->assertSee('no tiene visor integrado');
        $this->get(route('documents.file', $pdf))->assertOk();
        $this->get(route('documents.file', $docx))->assertStatus(415);
        $this->get(route('documents.file', ['version' => $docx, 'download' => 1]))->assertOk();
        $this->assertDatabaseHas('audit_events', ['action' => 'document.viewed', 'resource_id' => $pdf->id, 'user_id' => $loader->id, 'result' => 'success']);
        $this->assertDatabaseHas('audit_events', ['action' => 'document.downloaded', 'resource_id' => $docx->id, 'result' => 'success']);
    }

    // RF-BUS-001 / 002 / 003
    public function test_search_filters_sorting_and_pagination_respect_authorization(): void
    {
        $loader = $this->account('LOADER', ['name' => 'Carla']);
        $other = $this->account('LOADER', ['name' => 'Otro']);
        $a = $this->expediente($loader, ['name' => 'Alfa']);
        $b = $this->expediente($loader, ['name' => 'Beta']);
        $this->expediente($other, ['name' => 'Ajeno']);
        $a->documents()->create(['name' => 'Acta', 'type' => 'Acta de nacimiento']);

        $this->actingAs($loader)->get(route('expedientes.index'))->assertSee('Alfa')->assertSee('Beta')->assertDontSee('Ajeno')->assertSee('2 expedientes');
        $this->get(route('expedientes.index', ['q' => $b->folio]))->assertSee('Beta')->assertDontSee('Alfa');
        $this->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('expedientes.index', ['q' => 'Alfa']))
            ->assertOk()->assertSee('data-listado-resultados', false)->assertSee('Alfa')
            ->assertDontSee('Beta')->assertDontSee('Ajeno');
        $this->flushHeaders();
        $this->get(route('expedientes.index', ['sort' => 'titulo']))->assertSeeInOrder(['Alfa', 'Beta']);
        $this->get(route('expedientes.index', ['sort' => 'titulo_desc']))->assertSeeInOrder(['Beta', 'Alfa']);
        $this->get(route('expedientes.index', ['q' => 'Ajeno']))->assertSee('Sin coincidencias');
        for ($i = 0; $i < 9; $i++) {
            $this->expediente($loader);
        }
        $this->get(route('expedientes.index', ['per_page' => 25, 'document_type' => 'INE', 'sort' => 'titulo']))
            ->assertOk()->assertSee('1–10 de 11')->assertSee('Página 1 de 2')
            ->assertDontSee('id="filtro-type"', false)->assertDontSee('id="filtro-document-type"', false)
            ->assertDontSee('name="per_page"', false)
            ->assertViewHas('expedientes', fn ($items) => $items->perPage() === 10 && $items->count() === 10 && $items->total() === 11);
        $this->get(route('expedientes.index', ['page' => 2, 'sort' => 'titulo']))
            ->assertOk()->assertSee('11–11 de 11')->assertDontSee('Ajeno')
            ->assertViewHas('expedientes', fn ($items) => $items->count() === 1);
    }

    // RF-AUD-001 CA-03 / RF-AUD-002 / RF-AUD-003
    public function test_audit_is_admin_only_filterable_and_immutable(): void
    {
        $admin = $this->account('ADMIN');
        $loader = $this->account('LOADER');
        $e = $this->expediente($loader);
        $this->actingAs($loader)->put(route('expedientes.update', $e), ['name' => 'Renombrado']);
        $this->get(route('audit.index'))->assertForbidden();
        $this->actingAs($this->account())->get(route('audit.index'))->assertForbidden();

        $this->actingAs($admin)->get(route('audit.index', ['user_id' => $loader->id, 'action' => 'expediente.updated', 'from' => now()->toDateString(), 'to' => now()->toDateString()]))
            ->assertOk()->assertSee('Modificación de expediente')->assertDontSee('Acceso denegado a');
        $this->get(route('expedientes.show', $e))->assertSee('Historial del expediente')->assertSee('Modificación de metadatos');

        $event = AuditEvent::firstOrFail();
        $this->expectException(\LogicException::class);
        $event->update(['description' => 'alterado']);
    }

    // RF-VER-001 / RF-VER-002 / RF-VER-003
    public function test_versions_history_and_restoration(): void
    {
        $loader = $this->account('LOADER');
        $e = $this->expediente($loader);
        $v1 = $this->version($e, 1);
        $v2 = $this->version($e, 2);
        $reader = $this->account();
        $e->grants()->create(['user_id' => $reader->id, 'granted_by' => $loader->id, 'starts_at' => now()->subMinute()]);

        $this->actingAs($reader)->get(route('expedientes.show', $e))->assertSee('Versión 2')->assertDontSee('Versión 1');
        $this->get(route('documents.file', $v1))->assertForbidden();
        $this->get(route('documents.file', $v2))->assertOk();

        $this->actingAs($loader)->get(route('expedientes.show', $e))->assertSee('Versión 1')->assertSee('Vigente');
        $this->post(route('versions.restore', $v2))->assertSessionHasErrors('version');
        $this->post(route('versions.restore', $v1))->assertSessionHasNoErrors();
        $v3 = DocumentVersion::where('document_id', $v1->document_id)->where('version', 3)->firstOrFail();
        $this->assertSame(1, $v3->restored_from_version);
        $this->assertSame('Restaurado desde la versión 1', $v3->change_description);
        $this->assertSame($v1->hash, $v3->hash);
        $this->assertNotSame($v1->path, $v3->path);
        $this->assertSame(Storage::disk('local')->get($v1->path), Storage::disk('local')->get($v3->path));
        $this->assertSame(3, DocumentVersion::where('document_id', $v1->document_id)->count());
        $this->assertTrue($v3->isCurrent());
        $this->get(route('expedientes.show', $e))->assertSee('Restaurado');
        $this->assertDatabaseHas('audit_events', ['action' => 'version.restored', 'resource_id' => $v3->id]);
    }
}
