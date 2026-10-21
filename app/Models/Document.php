<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Documento del expediente. La baja es lógica (RF-DOC-004): conserva versiones, responsable, fecha y motivo. */
class Document extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function expediente()
    {
        return $this->belongsTo(Expediente::class);
    }

    public function versions()
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function latestVersion()
    {
        return $this->hasOne(DocumentVersion::class)->ofMany('version', 'max');
    }

    public function retiredBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
