<?php

namespace App\Enums;

/** Máquina de estados del expediente (RF-EXP-004). */
enum ExpedienteStatus: string
{
    case Activo = 'ACTIVO';
    case Archivado = 'ARCHIVADO';

    public function label(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Archivado => 'Archivado',
        };
    }

    /** Clase de .status-badge del sistema visual. */
    public function badge(): string
    {
        return match ($this) {
            self::Activo => 'status-activo',
            self::Archivado => 'status-inactivo',
        };
    }

    /** @return list<self> */
    public function transitions(): array
    {
        return match ($this) {
            self::Activo => [self::Archivado],
            self::Archivado => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->transitions(), true);
    }

    /** Solo activo admite modificaciones ordinarias (metadatos, cargas, bajas y versiones). */
    public function isEditable(): bool
    {
        return $this === self::Activo;
    }

    /** Verbo de la acción que lleva a este estado. */
    public function actionLabel(self $from): string
    {
        return match ($this) {
            self::Activo => 'Activar',
            self::Archivado => 'Archivar',
        };
    }
}
