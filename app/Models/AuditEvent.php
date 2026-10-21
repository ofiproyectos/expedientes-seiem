<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Evento de auditoría (RF-AUD-001). Solo se insertan: no se modifican ni se eliminan desde la aplicación. */
class AuditEvent extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = [
        'auth.login' => 'Inicio de sesión',
        'auth.logout' => 'Cierre de sesión',
        'auth.password_changed' => 'Cambio de contraseña',
        'access.denied' => 'Acceso denegado',
        'user.created' => 'Alta de usuario',
        'user.updated' => 'Modificación de usuario',
        'user.role_changed' => 'Cambio de rol',
        'user.activated' => 'Activación de usuario',
        'user.deactivated' => 'Desactivación de usuario',
        'user.password_reset' => 'Restablecimiento de contraseña',
        'expediente.created' => 'Creación de expediente',
        'expediente.updated' => 'Modificación de expediente',
        'document.uploaded' => 'Carga de documento',
        'document.updated' => 'Modificación de clasificación',
        'document.retired' => 'Baja de documento',
        'version.created' => 'Nueva versión',
        'version.restored' => 'Restauración de versión',
        'document.viewed' => 'Visualización',
        'document.downloaded' => 'Descarga',
        'grant.created' => 'Asignación de permiso',
        'grant.revoked' => 'Revocación de permiso',
    ];

    public const RESULTS = ['success' => 'Exitoso', 'failure' => 'Fallido', 'denied' => 'Denegado'];

    public const RESOURCES = ['user' => 'Usuario', 'expediente' => 'Expediente', 'document' => 'Documento', 'version' => 'Versión', 'grant' => 'Permiso', 'route' => 'Ruta'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los eventos de auditoría no se pueden modificar.'));
        static::deleting(fn () => throw new LogicException('Los eventos de auditoría no se pueden eliminar.'));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    public function resultBadge(): string
    {
        return match ($this->result) {
            'success' => 'status-activo',
            'denied' => 'status-rechazada',
            default => 'status-en_revision',
        };
    }
}
