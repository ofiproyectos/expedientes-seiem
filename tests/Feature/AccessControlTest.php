<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\DocumentVersion;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role = 'READER'): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function expediente(User $owner): Expediente
    {
        return Expediente::create(['name' => 'Expediente reservado', 'responsible_id' => $owner->id]);
    }

    private function document(Expediente $e): DocumentVersion
    {
        Storage::fake('local');
        $path = 'documents/'.Str::random(8).'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 test');
        $doc = $e->documents()->create(['name' => 'INE.pdf', 'type' => 'INE']);

        return $doc->versions()->create(['version' => 1, 'path' => $path, 'original_name' => 'INE.pdf', 'mime' => 'application/pdf', 'size' => 13, 'hash' => hash('sha256', $path), 'uploaded_by' => $e->responsible_id]);
    }

    private function grant(User $user, Expediente $e, array $data = []): AccessGrant
    {
        return $e->grants()->create([...['user_id' => $user->id, 'granted_by' => $e->responsible_id, 'starts_at' => now()->subMinute(), 'can_download' => false], ...$data]);
    }

    public function test_guest_cannot_read_files(): void
    {
        $version = $this->document($this->expediente($this->account('LOADER')));
        $this->get(route('documents.file', $version))->assertRedirect('/login');
    }

    public function test_reader_cannot_access_unshared_file_or_expediente(): void
    {
        $e = $this->expediente($this->account('LOADER'));
        $version = $this->document($e);
        $this->actingAs($this->account())->get(route('expedientes.show', $e))->assertForbidden();
        $this->get(route('documents.file', $version))->assertForbidden();
        $this->get(route('expedientes.index'))->assertOk()->assertDontSee('Expediente reservado');
    }

    public function test_reader_can_view_but_cannot_download_or_mutate(): void
    {
        $e = $this->expediente($this->account('LOADER'));
        $version = $this->document($e);
        $reader = $this->account();
        $this->grant($reader, $e);
        $this->actingAs($reader)->get(route('expedientes.show', $e))->assertOk()->assertDontSee('Descargar');
        $this->get(route('documents.file', $version))->assertOk();
        $this->get(route('documents.file', ['version' => $version, 'download' => 1]))->assertForbidden();
        $this->post(route('documents.store', $e))->assertForbidden();
        $this->post(route('documents.replace', $version->document))->assertForbidden();
        $this->put(route('expedientes.update', $e))->assertForbidden();
        $this->post(route('grants.store', $e))->assertForbidden();
        $this->post(route('expedientes.store'))->assertForbidden();
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('audit.index'))->assertForbidden();
    }

    public function test_document_grant_does_not_open_parent_or_other_documents(): void
    {
        $e = $this->expediente($this->account('LOADER'));
        $v = $this->document($e);
        $reader = $this->account();
        $this->grant($reader, $e, ['document_id' => $v->document_id, 'can_download' => true]);
        $this->actingAs($reader)->get(route('documents.file', ['version' => $v, 'download' => 1]))->assertOk();
        $this->get(route('expedientes.show', $e))->assertForbidden();
        $this->get(route('expedientes.index'))->assertOk()->assertSee('INE.pdf')->assertDontSee('Expediente reservado');
        $other = $e->documents()->create(['name' => 'Otro', 'type' => 'INE']);
        $otherVersion = $other->versions()->create([...$v->only('path', 'mime', 'original_name', 'hash', 'size', 'uploaded_by'), 'path' => 'documents/other.pdf', 'version' => 1]);
        $this->get(route('documents.file', $otherVersion))->assertForbidden();
    }

    public function test_expired_future_and_revoked_grants_are_denied_immediately(): void
    {
        $e = $this->expediente($this->account('LOADER'));
        $v = $this->document($e);
        $reader = $this->account();
        $grant = $this->grant($reader, $e, ['expires_at' => now()->subSecond()]);
        $this->actingAs($reader)->get(route('documents.file', $v))->assertForbidden();
        $grant->update(['expires_at' => null, 'starts_at' => now()->addDay()]);
        $this->get(route('documents.file', $v))->assertForbidden();
        $grant->update(['starts_at' => now()->subDay(), 'revoked_at' => now()]);
        $this->get(route('documents.file', $v))->assertForbidden();
    }

    public function test_inactive_user_session_is_rejected(): void
    {
        $reader = $this->account();
        $reader->update(['is_active' => false]);
        $this->actingAs($reader)->get(route('expedientes.index'))->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_loader_cannot_change_another_owners_expediente(): void
    {
        $e = $this->expediente($this->account('LOADER'));
        $this->actingAs($this->account('LOADER'))->put(route('expedientes.update', $e), ['name' => 'X'])->assertForbidden();
        $this->get(route('expedientes.show', $e))->assertForbidden();
        $this->get(route('users.index'))->assertForbidden();
    }

    public function test_owner_uploads_versions_rejects_duplicates_and_fake_extensions(): void
    {
        Storage::fake('local');
        $owner = $this->account('LOADER');
        $e = $this->expediente($owner);
        $this->actingAs($owner)->post(route('documents.store', $e), ['type' => 'INE', 'files' => [UploadedFile::fake()->createWithContent('ine.pdf', "%PDF-1.4\noriginal")]])->assertSessionHasNoErrors();
        $doc = $e->documents()->firstOrFail();
        $this->post(route('documents.replace', $doc), ['file' => UploadedFile::fake()->createWithContent('ine.pdf', "%PDF-1.4\nreemplazo"), 'change_description' => 'Escaneo legible'])->assertSessionHasNoErrors();
        $this->assertSame(2, $doc->versions()->count());
        $this->assertSame('Escaneo legible', $doc->versions()->where('version', 2)->value('change_description'));
        $this->post(route('documents.store', $e), ['type' => 'INE', 'files' => [UploadedFile::fake()->createWithContent('copy.pdf', "%PDF-1.4\noriginal")]])->assertSessionHasErrors('files');
        $this->post(route('documents.store', $e), ['type' => 'INE', 'files' => [UploadedFile::fake()->createWithContent('fake.pdf', '<?php echo 1;')]])->assertSessionHasErrors('files');
        $this->assertSame(1, $e->documents()->count());
        $this->assertSame(2, $doc->versions()->count());
    }

    public function test_login_updates_last_access_and_logout_ends_session(): void
    {
        $user = $this->account();
        $this->post('/login', ['username' => strtoupper($user->username), 'password' => 'password'])->assertRedirect(route('expedientes.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->get(route('expedientes.index'))->assertRedirect('/login');
    }

    public function test_login_locks_after_five_failed_attempts(): void
    {
        $user = $this->account();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => $user->username, 'password' => 'wrong'])->assertSessionHasErrors('username');
        }
        $this->post('/login', ['username' => $user->username, 'password' => 'password'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_admin_manages_users_and_cannot_deactivate_self(): void
    {
        $admin = $this->account('ADMIN');
        $this->actingAs($admin)->get(route('users.index'))->assertOk();
        $this->post(route('users.store'), ['name' => 'Lector Nuevo', 'username' => 'Lector.Nuevo', 'role' => 'READER', 'is_active' => 1, 'password' => 'Password123456', 'password_confirmation' => 'Password123456'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['username' => 'lector.nuevo', 'role' => 'READER', 'created_by' => $admin->id]);
        $this->put(route('users.update', $admin), ['name' => $admin->name, 'username' => $admin->username, 'role' => 'READER', 'is_active' => 0])->assertSessionHasErrors('role');
    }

    public function test_admin_grants_and_revokes_but_cannot_share_foreign_document(): void
    {
        $admin = $this->account('ADMIN');
        $owner = $this->account('LOADER');
        $e = $this->expediente($owner);
        $reader = $this->account();
        $this->actingAs($admin)->post(route('grants.store', $e), ['user_id' => $reader->id, 'starts_at' => now()->subMinute()->toDateTimeString()])->assertSessionHasNoErrors();
        $grant = $e->grants()->firstOrFail();
        $this->assertSame($admin->id, $grant->granted_by);
        $this->delete(route('grants.revoke', $grant))->assertSessionHasNoErrors();
        $this->assertNotNull($grant->fresh()->revoked_at);
        $this->assertSame($admin->id, $grant->fresh()->revoked_by);
        $v = $this->document($this->expediente($owner));
        $this->post(route('grants.store', $e), ['user_id' => $reader->id, 'document_id' => $v->document_id, 'starts_at' => now()->toDateTimeString()])->assertSessionHasErrors('document_id');
    }
}
