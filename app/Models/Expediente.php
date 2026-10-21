<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Expediente extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $expediente) {
            $expediente->folio ??= self::nextFolio();
        });
    }

    /** Folio EXP-AAAA-NNNNNN: consecutivo por año, tomado del contador con bloqueo para que dos altas simultáneas no lo repitan. */
    public static function nextFolio(): string
    {
        $year = (int) now()->format('Y');

        return DB::transaction(function () use ($year) {
            DB::table('expediente_folios')->insertOrIgnore(['year' => $year, 'last' => 0]);
            $next = DB::table('expediente_folios')->where('year', $year)->lockForUpdate()->value('last') + 1;
            DB::table('expediente_folios')->where('year', $year)->update(['last' => $next]);

            return sprintf('EXP-%d-%06d', $year, $next);
        });
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
