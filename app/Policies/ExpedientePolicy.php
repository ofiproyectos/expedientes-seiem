<?php

namespace App\Policies;

use App\Models\Expediente;
use App\Models\User;

/**
 * ADMIN: consulta todo, asigna permisos y solo cambia estados por excepción justificada.
 * LOADER: crea expedientes y administra los que tiene bajo su responsabilidad.
 * READER: consulta lo que se le comparte.
 */
class ExpedientePolicy
{
    public function view(User $user, Expediente $expediente): bool
    {
        return Expediente::visibleTo($user)->whereKey($expediente->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->isLoader();
    }

    /** Edición ordinaria: metadatos, cargas, versiones, bajas y restauraciones. Cerrado y archivado quedan protegidos. */
    public function update(User $user, Expediente $expediente): bool
    {
        return $this->isResponsible($user, $expediente) && $expediente->isEditable();
    }

    public function changeStatus(User $user, Expediente $expediente): bool
    {
        return $user->isAdmin() || $this->isResponsible($user, $expediente);
    }

    /** Historial de versiones y documentos dados de baja (RF-VER-002). */
    public function viewHistory(User $user, Expediente $expediente): bool
    {
        return $user->isAdmin() || $this->isResponsible($user, $expediente);
    }

    public function share(User $user, Expediente $expediente): bool
    {
        return $user->isAdmin();
    }

    public function viewAudit(User $user, Expediente $expediente): bool
    {
        return $user->isAdmin();
    }

    private function isResponsible(User $user, Expediente $expediente): bool
    {
        return $user->isLoader() && $expediente->responsible_id === $user->id;
    }
}
