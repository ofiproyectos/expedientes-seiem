<?php

namespace App\Policies;

use App\Models\AccessGrant;
use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        if ($document->trashed()) {
            return $this->viewHistory($user, $document);
        }

        return $this->expedientes()->view($user, $document->expediente) || $this->grants($user, $document)->exists();
    }

    public function download(User $user, Document $document): bool
    {
        if ($this->viewHistory($user, $document)) {
            return true;
        }

        return ! $document->trashed() && $this->grants($user, $document)->where('can_download', true)->exists();
    }

    /** Nueva versión, clasificación, baja y restauración. */
    public function update(User $user, Document $document): bool
    {
        return ! $document->trashed() && $this->expedientes()->update($user, $document->expediente);
    }

    /** Versiones anteriores y documentos dados de baja: ADMIN y LOADER responsable (RF-VER-002). */
    public function viewHistory(User $user, Document $document): bool
    {
        return $this->expedientes()->viewHistory($user, $document->expediente);
    }

    private function expedientes(): ExpedientePolicy
    {
        return new ExpedientePolicy;
    }

    private function grants(User $user, Document $document)
    {
        return AccessGrant::active()->where('user_id', $user->id)->where('expediente_id', $document->expediente_id)->where(fn ($q) => $q->whereNull('document_id')->orWhere('document_id', $document->id));
    }
}
