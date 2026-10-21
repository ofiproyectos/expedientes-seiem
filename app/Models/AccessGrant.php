<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AccessGrant extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['can_download' => true];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'can_download' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function expediente()
    {
        return $this->belongsTo(Expediente::class);
    }

    public function document()
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function grantor()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function revoker()
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /** Estado de vigencia para mostrar: [etiqueta, clase de .status-badge]. */
    public function status(): array
    {
        return match (true) {
            $this->revoked_at !== null => ['Revocado', 'status-inactivo'],
            $this->expires_at?->isPast() ?? false => ['Expirado', 'status-inactivo'],
            $this->starts_at->isFuture() => ['Programado', 'status-en_revision'],
            default => ['Activo', 'status-activo'],
        };
    }

    public function scopeActive(Builder $q): void
    {
        $q->whereNull('revoked_at')->where('starts_at', '<=', now())->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
