<?php

namespace App\Http\Controllers;

use App\Models\AccessGrant;
use App\Models\Expediente;
use App\Support\Audit;
use App\Support\PermissionBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccessGrantController extends Controller
{
    /** Solo ADMIN concede lectura y descarga a un READER. */
    public function store(Request $request, Expediente $expediente, PermissionBatch $batches)
    {
        Gate::authorize('share', $expediente);

        return DB::transaction(function () use ($request, $expediente, $batches) {
            Expediente::whereKey($expediente->id)->lockForUpdate()->firstOrFail();
            $data = $request->validate([
                'user_id' => ['required', Rule::exists('users', 'id')->where('is_active', true)->where('role', 'READER')],
                'document_id' => ['nullable', Rule::exists('documents', 'id')->where('expediente_id', $expediente->id)->whereNull('deleted_at')],
                'starts_at' => 'required|date', 'expires_at' => 'nullable|date|after:starts_at',
            ]);
            $batchData = $batches->validate([
                'user_ids' => [$data['user_id']], 'scope' => empty($data['document_id']) ? 'expediente' : 'documents',
                'document_ids' => empty($data['document_id']) ? [] : [$data['document_id']],
                'starts_at' => $data['starts_at'], 'expires_at' => $data['expires_at'] ?? null,
            ], $expediente);
            $plan = $batches->plan($expediente, $batchData);
            $batches->commit($expediente, $request->user(), ['id' => (string) Str::uuid(), 'data' => $batchData, 'fingerprint' => $plan['fingerprint']]);

            return back()->with('status', 'Permiso asignado.');
        });
    }

    /** RF-PRM-002: efecto inmediato en la siguiente solicitud del lector. */
    public function revoke(Request $request, AccessGrant $grant)
    {
        Gate::authorize('share', $grant->expediente);

        return DB::transaction(function () use ($grant, $request) {
            Expediente::whereKey($grant->expediente_id)->lockForUpdate()->firstOrFail();
            $grant->refresh();
            if (! $grant->revoked_at) {
                $grant->update(['revoked_at' => now(), 'revoked_by' => $request->user()->id]);
                Audit::record('grant.revoked', "Revocación del permiso de {$grant->user->username}", $grant, ['reader' => $grant->user->username, 'document_id' => $grant->document_id]);
            }

            return back()->with('status', 'Permiso revocado.');
        });
    }
}
