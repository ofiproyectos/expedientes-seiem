<?php

namespace App\Support;

use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de eventos de auditoría (RF-AUD-001).
 * Nunca recibe contraseñas, tokens ni contenido documental: solo identificadores y metadatos descriptivos.
 */
class Audit
{
    private const SENSITIVE = ['password', 'password_confirmation', 'current_password', 'remember_token', '_token', 'token'];

    public static function record(string $action, string $description, ?Model $resource = null, array $metadata = [], string $result = 'success', ?User $user = null): AuditEvent
    {
        $request = request();
        $user ??= $request?->user();

        return AuditEvent::create([
            'user_id' => $user?->id,
            'username' => $user?->username ?? ($metadata['username'] ?? null),
            'action' => $action,
            'result' => $result,
            'resource_type' => $resource ? self::resourceType($resource) : ($metadata['resource_type'] ?? null),
            'resource_id' => $resource?->getKey(),
            'expediente_id' => $resource ? self::expedienteId($resource) : null,
            'description' => mb_substr($description, 0, 500),
            'metadata' => self::clean($metadata) ?: null,
            'ip_address' => $request?->ip(),
        ]);
    }

    public static function denied(string $description, ?Model $resource = null, array $metadata = []): AuditEvent
    {
        return self::record('access.denied', $description, $resource, $metadata, 'denied');
    }

    private static function resourceType(Model $resource): string
    {
        return match (true) {
            $resource instanceof User => 'user',
            $resource instanceof Expediente => 'expediente',
            $resource instanceof Document => 'document',
            $resource instanceof DocumentVersion => 'version',
            default => 'grant',
        };
    }

    private static function expedienteId(Model $resource): ?int
    {
        return match (true) {
            $resource instanceof Expediente => $resource->id,
            $resource instanceof DocumentVersion => $resource->document()->withTrashed()->value('expediente_id'),
            default => $resource->getAttribute('expediente_id'),
        };
    }

    private static function clean(array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            if (in_array($key, self::SENSITIVE, true)) {
                unset($metadata[$key]);
            } elseif (is_array($value)) {
                $metadata[$key] = self::clean($value);
            }
        }
        unset($metadata['resource_type']);

        return $metadata;
    }
}
