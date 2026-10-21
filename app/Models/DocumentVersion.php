<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Versión inmutable de un documento: cada una conserva su archivo independiente (RF-VER-001). */
class DocumentVersion extends Model
{
    public const VIEWABLE = ['application/pdf', 'image/jpeg', 'image/png'];

    protected $guarded = ['id'];

    public function document()
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isViewable(): bool
    {
        return in_array($this->mime, self::VIEWABLE, true);
    }

    public function isCurrent(): bool
    {
        return $this->version === (int) DocumentVersion::where('document_id', $this->document_id)->max('version');
    }

    public function format(): string
    {
        return strtoupper(pathinfo($this->original_name, PATHINFO_EXTENSION)) ?: $this->mime;
    }
}
