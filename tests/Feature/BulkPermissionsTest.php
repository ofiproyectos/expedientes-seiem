<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\AuditEvent;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class BulkPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $owner = User::factory()->create(['role' => 'LOADER']);
        $reader = User::factory()->create(['role' => 'READER']);
        $other = User::factory()->create(['role' => 'READER']);
        $expediente = Expediente::create(['name' => 'Expediente masivo', 'responsible_id' => $owner->id]);
        $documents = collect(range(1, 3))->map(fn ($i) => $expediente->documents()->create(['name' => "Documento $i", 'type' => 'INE']));

        return compact('admin', 'owner', 'reader', 'other', 'expediente', 'documents');
    }

    private function payload(array $f): array
    {
        return ['user_ids' => [$f['reader']->id, $f['other']->id], 'document_ids' => $f['documents']->pluck('id')->all(),
            'scope' => 'documents', 'can_download' => false, 'starts_at' => now()->subMinute()->toDateTimeString()];
    }

    private function preview(array $f, ?array $data = null): string
    {
        return $this->actingAs($f['admin'])->postJson(route('permissions.preview', $f['expediente']), $data ?? $this->payload($f))
            ->assertOk()->json('token');
    }

    public function test_batch_creates_cross_product_with_audit_and_retries_are_idempotent(): void
    {
        $f = $this->fixture();
        $token = $this->preview($f);
        $this->post(route('permissions.store', $f['expediente']), ['review_token' => $token])->assertRedirect();
        $this->assertSame(6, AccessGrant::count());
        $this->assertSame(6, AuditEvent::where('action', 'grant.created')->count());
        $this->assertSame(1, AuditEvent::where('action', 'grant.batch_created')->count());
        $this->post(route('permissions.store', $f['expediente']), ['review_token' => $token])->assertRedirect();
        $this->assertSame(6, AccessGrant::count());
        $this->assertSame(1, DB::table('permission_batches')->count());
        $this->postJson(route('permissions.preview', $f['expediente']), $this->payload($f))->assertOk()->assertJson(['created' => 0, 'skipped' => 6]);
    }

    public function test_only_admin_can_open_preview_commit_manage_or_revoke(): void
    {
        $f = $this->fixture();
        $token = $this->preview($f);
        foreach ([$f['owner'], $f['reader']] as $user) {
            $this->actingAs($user)->get(route('permissions.create', $f['expediente']))->assertForbidden();
            $this->postJson(route('permissions.preview', $f['expediente']), $this->payload($f))->assertForbidden();
            $this->post(route('permissions.store', $f['expediente']), ['review_token' => $token])->assertForbidden();
            $this->get(route('permissions.reader', [$f['expediente'], $f['reader']]))->assertForbidden();
            $this->post(route('permissions.revoke', $f['expediente']), ['mode' => 'readers', 'selection' => json_encode([$f['reader']->id])])->assertForbidden();
        }
        $this->assertSame(0, AccessGrant::count());
    }

    public function test_invalid_inactive_foreign_or_deleted_targets_are_rejected_without_partial_grants(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin']);
        $data = $this->payload($f);
        $f['reader']->update(['is_active' => false]);
        $this->postJson(route('permissions.preview', $f['expediente']), $data)->assertUnprocessable()->assertJsonValidationErrors('user_ids.0');
        $f['reader']->update(['is_active' => true]);
        $foreign = Expediente::create(['name' => 'Ajeno', 'responsible_id' => $f['owner']->id])->documents()->create(['name' => 'Ajeno', 'type' => 'INE']);
        $this->postJson(route('permissions.preview', $f['expediente']), [...$data, 'document_ids' => [$foreign->id]])->assertUnprocessable();
        $f['documents'][0]->delete();
        $this->postJson(route('permissions.preview', $f['expediente']), $data)->assertUnprocessable();
        $this->assertSame(0, AccessGrant::count());
    }

    public function test_changed_selection_is_revalidated_after_preview(): void
    {
        $f = $this->fixture();
        $token = $this->preview($f);
        $f['reader']->update(['is_active' => false]);
        $this->postJson(route('permissions.store', $f['expediente']), ['review_token' => $token])->assertUnprocessable();
        $this->assertSame(0, AccessGrant::count());
        $f['reader']->update(['is_active' => true]);
        $token = $this->preview($f);
        $f['expediente']->grants()->create(['user_id' => $f['reader']->id, 'granted_by' => $f['admin']->id, 'starts_at' => now()]);
        $this->postJson(route('permissions.store', $f['expediente']), ['review_token' => $token])->assertUnprocessable()->assertJsonValidationErrors('review_token');
        $this->assertSame(1, AccessGrant::count());
    }

    public function test_coverage_ignores_the_legacy_download_flag_and_respects_the_entire_requested_interval(): void
    {
        $f = $this->fixture();
        $data = $this->payload($f);
        $f['expediente']->grants()->create(['user_id' => $f['reader']->id, 'granted_by' => $f['admin']->id, 'starts_at' => now()->subDay(), 'can_download' => false]);
        $this->actingAs($f['admin'])->postJson(route('permissions.preview', $f['expediente']), $data)->assertOk()->assertJson(['created' => 3, 'skipped' => 3]);
        $this->postJson(route('permissions.preview', $f['expediente']), [...$data, 'can_download' => true])->assertOk()->assertJson(['created' => 3, 'skipped' => 3]);
        AccessGrant::first()->update(['expires_at' => now()->addHour()]);
        $this->postJson(route('permissions.preview', $f['expediente']), $data)->assertOk()->assertJson(['created' => 6, 'skipped' => 0]);
        $this->postJson(route('permissions.preview', $f['expediente']), [...$data, 'expires_at' => now()->addMinutes(30)->toDateTimeString()])->assertOk()->assertJson(['created' => 3, 'skipped' => 3]);
    }

    public function test_signed_review_cannot_be_used_by_another_admin_or_expediente_and_expires(): void
    {
        $f = $this->fixture();
        $token = $this->preview($f);
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->actingAs($admin)->postJson(route('permissions.store', $f['expediente']), ['review_token' => $token])->assertForbidden();
        $this->actingAs($f['admin'])->postJson(route('permissions.store', $f['expediente']), ['review_token' => 'alterado'])->assertUnprocessable();
        $other = Expediente::create(['name' => 'Otro', 'responsible_id' => $f['owner']->id]);
        $this->postJson(route('permissions.store', $other), ['review_token' => $token])->assertForbidden();
        $this->travel(31)->minutes();
        $this->postJson(route('permissions.store', $f['expediente']), ['review_token' => $token])->assertUnprocessable();
        $this->assertSame(0, AccessGrant::count());
    }

    public function test_a_failure_rolls_back_both_grants_and_audit(): void
    {
        $f = $this->fixture();
        $token = $this->preview($f);
        AccessGrant::creating(function ($grant) use ($f) {
            if ($grant->document_id === $f['documents'][1]->id) {
                throw new \RuntimeException('Fallo de almacenamiento simulado');
            }
        });
        try {
            $this->postJson(route('permissions.store', $f['expediente']), ['review_token' => $token])->assertStatus(500);
            $this->assertSame(0, AccessGrant::count());
            $this->assertSame(0, DB::table('permission_batches')->count());
            $this->assertSame(0, AuditEvent::where('action', 'grant.created')->count());
        } finally {
            AccessGrant::flushEventListeners();
        }
    }

    public function test_revoke_specific_sources_keeps_overlapping_access_then_revoke_reader_removes_all(): void
    {
        $f = $this->fixture();
        $general = $f['expediente']->grants()->create(['user_id' => $f['reader']->id, 'granted_by' => $f['admin']->id, 'starts_at' => now()->subMinute()]);
        $specific = $f['expediente']->grants()->create(['user_id' => $f['reader']->id, 'document_id' => $f['documents'][0]->id, 'granted_by' => $f['admin']->id, 'starts_at' => now()->subMinute(), 'can_download' => true]);
        $this->actingAs($f['admin'])->get(route('permissions.reader', [$f['expediente'], $f['reader']]))->assertOk()->assertSee('Expediente completo')->assertSee('2 permisos vigentes');
        $this->post(route('permissions.revoke', $f['expediente']), ['mode' => 'grants', 'selection' => json_encode([$specific->id])])->assertRedirect();
        $this->assertNotNull($specific->fresh()->revoked_at);
        $this->assertNull($general->fresh()->revoked_at);
        $this->assertTrue(Gate::forUser($f['reader'])->allows('view', $f['documents'][0]));
        $this->assertTrue(Gate::forUser($f['reader'])->allows('download', $f['documents'][0]));
        $this->post(route('permissions.revoke', $f['expediente']), ['mode' => 'readers', 'selection' => json_encode([$f['reader']->id])])->assertRedirect();
        $this->assertFalse(Gate::forUser($f['reader'])->allows('view', $f['documents'][0]));
    }

    public function test_foreign_revocation_selection_cannot_revoke_valid_members_of_the_same_request(): void
    {
        $f = $this->fixture();
        $own = $f['expediente']->grants()->create(['user_id' => $f['reader']->id, 'granted_by' => $f['admin']->id, 'starts_at' => now()]);
        $other = Expediente::create(['name' => 'Otro', 'responsible_id' => $f['owner']->id])->grants()->create(['user_id' => $f['reader']->id, 'granted_by' => $f['admin']->id, 'starts_at' => now()]);
        $this->actingAs($f['admin'])->postJson(route('permissions.revoke', $f['expediente']), ['mode' => 'grants', 'selection' => json_encode([$own->id, $other->id])])->assertUnprocessable();
        $this->assertSame(0, AccessGrant::whereNotNull('revoked_at')->count());
    }

    public function test_create_restores_selection_and_whole_expediente_covers_future_documents(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin'])->get(route('permissions.create', ['expediente' => $f['expediente'], 'documents' => [$f['documents'][0]->id]]))
            ->assertOk()->assertSee('data-permission-wizard', false)->assertSee('Seleccionar todos los resultados')->assertSee('Documentos específicos');
        $data = [...$this->payload($f), 'scope' => 'expediente', 'document_ids' => []];
        $token = $this->preview($f, $data);
        $this->post(route('permissions.store', $f['expediente']), ['review_token' => $token])->assertRedirect();
        $future = $f['expediente']->documents()->create(['name' => 'Futuro', 'type' => 'INE']);
        $this->assertTrue(Gate::forUser($f['reader'])->allows('view', $future));
        $this->assertSame(2, AccessGrant::whereNull('document_id')->count());
    }

    public function test_empty_permissions_use_the_shared_empty_state_without_inactive_controls_or_audit_history(): void
    {
        $f = $this->fixture();
        foreach ($f['documents'] as $document) {
            $document->versions()->create(['version' => 1, 'path' => 'test-'.$document->id.'.pdf', 'original_name' => 'test.pdf',
                'mime' => 'application/pdf', 'size' => 100, 'hash' => hash('sha256', (string) $document->id), 'uploaded_by' => $f['owner']->id]);
        }
        $grant = $f['expediente']->grants()->create(['user_id' => $f['reader']->id, 'granted_by' => $f['admin']->id, 'starts_at' => now()->subMinute()]);
        $this->actingAs($f['admin'])->post(route('permissions.revoke', $f['expediente']), ['mode' => 'grants', 'selection' => json_encode([$grant->id])])->assertRedirect();
        $this->get(route('expedientes.show', $f['expediente']))->assertOk()
            ->assertSee('Sin permisos de lectura')->assertDontSee('id="permission-readers"', false)
            ->assertDontSee('Revocar seleccionados')->assertDontSee('Historial de permisos')->assertDontSee('Auditoría');
        $this->get(route('permissions.reader', [$f['expediente'], $f['reader']]))->assertOk()->assertSee('Sin acceso a documentos')->assertDontSee('Historial de permisos');
        $this->get(route('users.edit', $f['reader']))->assertOk()->assertDontSee($f['expediente']->name)->assertDontSee('Otorgado por');
        $this->assertDatabaseHas('access_grants', ['id' => $grant->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'grant.revoked', 'resource_id' => $grant->id]);
    }

    public function test_whole_expediente_replaces_a_fully_covered_document_without_deleting_or_reviving_it(): void
    {
        $f = $this->fixture();
        $old = $f['expediente']->grants()->create(['user_id' => $f['reader']->id, 'document_id' => $f['documents'][0]->id,
            'granted_by' => $f['admin']->id, 'starts_at' => now()->subDays(3)]);
        $data = ['user_ids' => [$f['reader']->id], 'scope' => 'expediente', 'document_ids' => [], 'can_download' => false];
        $preview = $this->actingAs($f['admin'])->postJson(route('permissions.preview', $f['expediente']), $data)
            ->assertOk()->assertJson(['created' => 1, 'consolidated' => 1]);
        $token = $preview->json('token');
        $this->post(route('permissions.store', $f['expediente']), ['review_token' => $token])->assertRedirect();
        $general = AccessGrant::whereNull('document_id')->firstOrFail();
        $this->assertSame($general->id, $old->fresh()->superseded_by);
        $this->assertNotNull($old->fresh()->revoked_at);
        $this->assertSame(2, AccessGrant::count());
        $this->assertSame(1, AccessGrant::active()->count());
        $this->assertDatabaseHas('audit_events', ['action' => 'grant.consolidated', 'resource_id' => $old->id]);
        $this->post(route('permissions.store', $f['expediente']), ['review_token' => $token])->assertRedirect();
        $this->assertSame(1, AuditEvent::where('action', 'grant.consolidated')->count());
        $this->post(route('permissions.revoke', $f['expediente']), ['mode' => 'grants', 'selection' => json_encode([$general->id])])->assertRedirect();
        $this->assertSame(0, AccessGrant::active()->count());
        $this->assertFalse(Gate::forUser($f['reader'])->allows('view', $f['documents'][0]));
    }

    public function test_consolidation_preserves_extra_duration_and_access_before_a_scheduled_general_grant(): void
    {
        $f = $this->fixture();
        foreach (['duration', 'future'] as $case) {
            $reader = User::factory()->create(['role' => 'READER']);
            $old = $f['expediente']->grants()->create(['user_id' => $reader->id, 'document_id' => $f['documents'][0]->id,
                'granted_by' => $f['admin']->id, 'starts_at' => now()->subDay(), 'can_download' => $case === 'download']);
            $data = ['user_ids' => [$reader->id], 'scope' => 'expediente', 'document_ids' => [],
                'starts_at' => $case === 'future' ? now()->addDay()->toDateTimeString() : null,
                'expires_at' => $case === 'duration' ? now()->addWeek()->toDateTimeString() : null];
            $preview = $this->actingAs($f['admin'])->postJson(route('permissions.preview', $f['expediente']), $data)
                ->assertOk()->assertJson(['created' => 1, 'consolidated' => 0]);
            $this->post(route('permissions.store', $f['expediente']), ['review_token' => $preview->json('token')])->assertRedirect();
            $this->assertNull($old->fresh()->revoked_at);
            $this->assertNull($old->fresh()->superseded_by);
            $this->assertTrue(Gate::forUser($reader)->allows('view', $f['documents'][0]));
        }
    }

    public function test_scheduled_document_can_be_consolidated_when_general_starts_earlier_and_covers_download(): void
    {
        $f = $this->fixture();
        $old = $f['expediente']->grants()->create(['user_id' => $f['reader']->id, 'document_id' => $f['documents'][0]->id,
            'granted_by' => $f['admin']->id, 'starts_at' => now()->addWeek(), 'expires_at' => now()->addWeeks(2), 'can_download' => true]);
        $data = ['user_ids' => [$f['reader']->id], 'scope' => 'expediente', 'document_ids' => [],
            'starts_at' => now()->addDay()->toDateTimeString(), 'can_download' => true];
        $preview = $this->actingAs($f['admin'])->postJson(route('permissions.preview', $f['expediente']), $data)
            ->assertOk()->assertJson(['consolidated' => 1]);
        $this->post(route('permissions.store', $f['expediente']), ['review_token' => $preview->json('token')])->assertRedirect();
        $this->assertNotNull($old->fresh()->superseded_by);
        $this->assertSame(0, AccessGrant::active()->count());
        $this->travel(8)->days();
        $this->assertTrue(Gate::forUser($f['reader'])->allows('download', $f['documents'][0]));
    }

    public function test_existing_redundant_grants_can_be_consolidated_without_creating_another_general_grant(): void
    {
        $f = $this->fixture();
        $specific = $f['expediente']->grants()->create(['user_id' => $f['reader']->id, 'document_id' => $f['documents'][0]->id,
            'granted_by' => $f['admin']->id, 'starts_at' => now()->subDay()]);
        $general = $f['expediente']->grants()->create(['user_id' => $f['reader']->id, 'granted_by' => $f['admin']->id, 'starts_at' => now()->subHour()]);
        $preview = $this->actingAs($f['admin'])->postJson(route('permissions.preview', $f['expediente']), [
            'user_ids' => [$f['reader']->id], 'scope' => 'expediente', 'document_ids' => [],
        ])->assertOk()->assertJson(['created' => 0, 'skipped' => 1, 'consolidated' => 1]);
        $this->post(route('permissions.store', $f['expediente']), ['review_token' => $preview->json('token')])->assertRedirect();
        $this->assertSame($general->id, $specific->fresh()->superseded_by);
        $this->assertSame(2, AccessGrant::count());
    }

    public function test_single_assignment_uses_the_same_consolidation_rules(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin'])->post(route('grants.store', $f['expediente']), [
            'user_id' => $f['reader']->id, 'document_id' => $f['documents'][0]->id, 'starts_at' => now()->subDay()->toDateTimeString(),
        ])->assertRedirect();
        $old = AccessGrant::firstOrFail();
        $this->post(route('grants.store', $f['expediente']), [
            'user_id' => $f['reader']->id, 'starts_at' => now()->toDateTimeString(),
        ])->assertRedirect();
        $this->assertNotNull($old->fresh()->superseded_by);
        $this->assertSame(1, AccessGrant::active()->count());
    }
}
