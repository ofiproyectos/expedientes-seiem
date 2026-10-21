<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Evento de auditoría (RF-AUD-001). Solo se insertan: no se modifican ni se eliminan desde la aplicación. */
class AuditEvent extends Model
{
    public const UPDATED_AT = null;

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
}
