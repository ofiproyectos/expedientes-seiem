<?php

namespace App\Http\Controllers;

use App\Models\AccessGrant;
use App\Models\Expediente;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AccessGrantController extends Controller
{
    /** RF-PRM-001/004: solo ADMIN concede lectura a un READER; la descarga es una autorización adicional. */
    public function store(Request $request, Expediente $expediente)
    {
        Gate::authorize('share', $expediente);
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where('is_active', true)->where('role', 'READER')],
            'document_id' => ['nullable', Rule::exists('documents', 'id')->where('expediente_id', $expediente->id)->whereNull('deleted_at')],
            'starts_at' => 'required|date', 'expires_at' => 'nullable|date|after:starts_at', 'can_download' => 'sometimes|boolean',
        ]);
        $grant = $expediente->grants()->create([...$data, 'granted_by' => $request->user()->id, 'can_download' => $request->boolean('can_download')]);
        $grant->load('user', 'document');
        Audit::record('grant.created', "Permiso de lectura para {$grant->user->username} sobre ".($grant->document ? "«{$grant->document->name}»" : 'el expediente completo'), $grant, [
            'reader' => $grant->user->username, 'document_id' => $grant->document_id, 'can_download' => $grant->can_download,
            'starts_at' => $grant->starts_at->toDateTimeString(), 'expires_at' => $grant->expires_at?->toDateTimeString(),
        ]);

        return back()->with('status', 'Permiso asignado.');
    }

    /** RF-PRM-002: efecto inmediato en la siguiente solicitud del lector. */
    public function revoke(Request $request, AccessGrant $grant)
    {
        Gate::authorize('share', $grant->expediente);
        if (! $grant->revoked_at) {
            $grant->update(['revoked_at' => now(), 'revoked_by' => $request->user()->id]);
            Audit::record('grant.revoked', "Revocación del permiso de {$grant->user->username}", $grant, ['reader' => $grant->user->username, 'document_id' => $grant->document_id]);
        }

        return back()->with('status', 'Permiso revocado.');
    }
}
