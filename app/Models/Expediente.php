<?php

namespace App\Models;

use App\Enums\ExpedienteStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Expediente extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'ACTIVO'];

    protected function casts(): array
    {
        return ['status' => ExpedienteStatus::class];
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function grants()
    {
        return $this->hasMany(AccessGrant::class);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /** ADMIN consulta todos; LOADER los que tiene bajo su responsabilidad; READER los compartidos completos. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }
        if ($user->isLoader()) {
            $query->where('responsible_id', $user->id);

            return;
        }
        $query->whereHas('grants', fn ($g) => $g->active()->where('user_id', $user->id)->whereNull('document_id'));
    }
}
