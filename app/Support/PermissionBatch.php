<?php

namespace App\Support;

use App\Models\Expediente;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PermissionBatch
{
    public function validate(array $input, Expediente $expediente): array
    {
        $data = Validator::make($input, [
            'user_ids' => 'required|array|min:1|max:1000',
            'user_ids.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')->where('is_active', true)->where('role', 'READER')],
            'scope' => 'required|in:expediente,documents',
            'document_ids' => 'required_if:scope,documents|array|max:1000',
            'document_ids.*' => ['required', 'integer', 'distinct', Rule::exists('documents', 'id')->where('expediente_id', $expediente->id)->whereNull('deleted_at')],
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
        ])->validate();
        if ($data['scope'] === 'documents' && empty($data['document_ids'])) {
            throw ValidationException::withMessages(['document_ids' => 'Selecciona al menos un documento.']);
        }
        $data['user_ids'] = array_map('intval', $data['user_ids']);
        sort($data['user_ids']);
        $data['document_ids'] = $data['scope'] === 'documents' ? array_map('intval', $data['document_ids']) : [];
        sort($data['document_ids']);
        if (count($data['user_ids']) * max(1, count($data['document_ids'])) > 20000) {
            throw ValidationException::withMessages(['user_ids' => 'Este lote supera 20 000 permisos. Divide la selección en varios lotes.']);
        }
        $data['starts_at'] = Carbon::parse($data['starts_at'] ?? now())->toDateTimeString();
        $data['expires_at'] = empty($data['expires_at']) ? null : Carbon::parse($data['expires_at'])->toDateTimeString();
        if ($data['expires_at'] && ($data['expires_at'] <= $data['starts_at'] || Carbon::parse($data['expires_at'])->lte(now()))) {
            throw ValidationException::withMessages(['expires_at' => 'La expiración debe ser posterior al inicio y a la fecha actual.']);
        }

        return $data;
    }

    /** Un permiso cubre el lote si incluye todo el intervalo solicitado. La lectura incluye descarga. */
    public function plan(Expediente $expediente, array $data): array
    {
        $existing = $expediente->grants()->whereIn('user_id', $data['user_ids'])->orderBy('id')->get();
        $users = User::whereIn('id', $data['user_ids'])->orderBy('id')->get(['id', 'name', 'username', 'role', 'is_active']);
        $documents = $expediente->documents()->whereIn('id', $data['document_ids'])->orderBy('id')->get(['id', 'name', 'updated_at']);
        $fingerprint = hash('sha256', json_encode([$existing->toArray(), $users->toArray(), $documents->toArray()]));
        $byUser = $existing->groupBy('user_id');
        $create = [];
        $skipped = 0;
        $targets = $data['scope'] === 'expediente' ? [null] : $data['document_ids'];
        foreach ($data['user_ids'] as $userId) {
            foreach ($targets as $documentId) {
                $relevant = ($byUser[$userId] ?? collect())->filter(fn ($grant) => ! $grant->revoked_at
                    && ($grant->document_id === null || $grant->document_id === $documentId));
                $covered = $relevant->contains(fn ($grant) => $grant->starts_at->toDateTimeString() <= $data['starts_at']
                    && ($grant->expires_at === null || ($data['expires_at'] !== null && $grant->expires_at->toDateTimeString() >= $data['expires_at'])));
                if ($covered) {
                    $skipped++;
                } else {
                    $create[] = ['user_id' => $userId, 'document_id' => $documentId];
                }
            }
        }

        $asOf = now()->toDateTimeString();
        $providers = collect($create)->map(fn ($target) => [
            ...$target, 'key' => 'new:'.$target['user_id'].':'.$target['document_id'],
            'starts_at' => $data['starts_at'], 'expires_at' => $data['expires_at'],
        ])->concat($existing->filter(fn ($g) => ! $g->revoked_at && (! $g->expires_at || $g->expires_at->isFuture()))->map(fn ($g) => [
            'key' => 'id:'.$g->id, 'id' => $g->id, 'user_id' => $g->user_id, 'document_id' => $g->document_id,
            'starts_at' => $g->starts_at->toDateTimeString(), 'expires_at' => $g->expires_at?->toDateTimeString(),
        ]))->sortBy(fn ($p) => $p['document_id'] === null ? 0 : 1);
        $providersByUser = $providers->groupBy('user_id');
        $consolidate = [];
        foreach ($existing as $grant) {
            if ($grant->revoked_at || ($grant->expires_at && $grant->expires_at->toDateTimeString() <= $asOf)) {
                continue;
            }
            $remainingStart = max($asOf, $grant->starts_at->toDateTimeString());
            $provider = ($providersByUser[$grant->user_id] ?? collect())->first(fn ($p) => ($p['document_id'] === null || $p['document_id'] === $grant->document_id)
                // Entre permisos equivalentes existentes conserva el primero; evita referencias cíclicas.
                && (! isset($p['id']) || ($p['document_id'] === null && $grant->document_id !== null) || $p['id'] < $grant->id)
                && $p['starts_at'] <= $remainingStart
                && ($p['expires_at'] === null || ($grant->expires_at && $p['expires_at'] >= $grant->expires_at->toDateTimeString())));
            if ($provider) {
                $consolidate[$grant->id] = $provider['key'];
            }
        }
        $fingerprint = hash('sha256', json_encode([$fingerprint, $consolidate]));

        return compact('create', 'skipped', 'fingerprint', 'users', 'documents', 'consolidate');
    }

    public function commit(Expediente $expediente, User $actor, array $review): object
    {
        return DB::transaction(function () use ($expediente, $actor, $review) {
            // Serializa las asignaciones y revocaciones del mismo expediente.
            Expediente::whereKey($expediente->id)->lockForUpdate()->firstOrFail();
            $completed = DB::table('permission_batches')->where('id', $review['id'])->first();
            if ($completed) {
                return $completed;
            }
            // Bloquea también los sujetos: no pueden desactivarse o darse de baja entre validar y guardar.
            User::whereIn('id', $review['data']['user_ids'])->orderBy('id')->lockForUpdate()->get();
            $expediente->documents()->whereIn('id', $review['data']['document_ids'])->orderBy('id')->lockForUpdate()->get();
            $data = $this->validate($review['data'], $expediente);
            $plan = $this->plan($expediente, $data);
            if (! hash_equals($review['fingerprint'], $plan['fingerprint'])) {
                throw ValidationException::withMessages(['review_token' => 'Los lectores, documentos o permisos cambiaron. Revisa el lote de nuevo antes de asignarlo.']);
            }
            DB::table('permission_batches')->insert([
                'id' => $review['id'], 'expediente_id' => $expediente->id, 'granted_by' => $actor->id,
                'created_count' => count($plan['create']), 'skipped_count' => $plan['skipped'], 'consolidated_count' => count($plan['consolidate']), 'created_at' => now(),
            ]);
            $usernames = $plan['users']->pluck('username', 'id');
            $documentNames = $plan['documents']->pluck('name', 'id');
            $newIds = [];
            foreach ($plan['create'] as $target) {
                $grant = $expediente->grants()->create([
                    ...$target, 'batch_id' => $review['id'], 'granted_by' => $actor->id,
                    'starts_at' => $data['starts_at'], 'expires_at' => $data['expires_at'],
                ]);
                $newIds['new:'.$target['user_id'].':'.$target['document_id']] = $grant->id;
                $scopeName = $target['document_id'] === null ? 'expediente completo' : $documentNames[$target['document_id']];
                Audit::record('grant.created', "Permiso por lote para {$usernames[$target['user_id']]} sobre {$scopeName}", $grant, [
                    'batch_id' => $review['id'], ...$target, 'starts_at' => $data['starts_at'],
                    'expires_at' => $data['expires_at'],
                ]);
            }
            foreach ($plan['consolidate'] as $oldId => $provider) {
                // Resuelve el destino definitivo si un permiso general antiguo también se consolida.
                while (str_starts_with($provider, 'id:') && isset($plan['consolidate'][(int) substr($provider, 3)])) {
                    $provider = $plan['consolidate'][(int) substr($provider, 3)];
                }
                $replacementId = $newIds[$provider] ?? (int) substr($provider, 3);
                $old = $expediente->grants()->findOrFail($oldId);
                $old->update(['revoked_at' => now(), 'revoked_by' => $actor->id, 'superseded_by' => $replacementId]);
                Audit::record('grant.consolidated', 'Permiso sustituido por otro que cubre todo su acceso restante', $old, [
                    'batch_id' => $review['id'], 'superseded_by' => $replacementId,
                ]);
            }
            Audit::record('grant.batch_created', 'Asignación de permisos por lote', $expediente, [
                'batch_id' => $review['id'], 'readers' => $data['user_ids'], 'documents' => $data['document_ids'],
                'scope' => $data['scope'], 'created' => count($plan['create']), 'skipped' => $plan['skipped'], 'consolidated' => count($plan['consolidate']),
            ]);

            return DB::table('permission_batches')->where('id', $review['id'])->first();
        });
    }
}
