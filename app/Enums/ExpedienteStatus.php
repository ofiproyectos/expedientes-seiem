<?php

namespace App\Enums;

/** Máquina de estados del expediente (RF-EXP-004). */
enum ExpedienteStatus: string
{
    case Borrador = 'BORRADOR';
    case Activo = 'ACTIVO';
    case Cerrado = 'CERRADO';
    case Archivado = 'ARCHIVADO';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Activo => 'Activo',
            self::Cerrado => 'Cerrado',
            self::Archivado => 'Archivado',
        };
    }

    /** Clase de .status-badge del sistema visual. */
    public function badge(): string
    {
        return match ($this) {
            self::Borrador => 'status-en_revision',
            self::Activo => 'status-activo',
            self::Cerrado, self::Archivado => 'status-inactivo',
        };
    }

    /** @return list<self> */
    public function transitions(): array
    {
        return match ($this) {
            self::Borrador => [self::Activo],
            self::Activo => [self::Cerrado],
            self::Cerrado => [self::Activo, self::Archivado],
            self::Archivado => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->transitions(), true);
    }

    /** Solo borrador y activo admiten modificaciones ordinarias (metadatos, cargas, bajas y versiones). */
    public function isEditable(): bool
    {
        return in_array($this, [self::Borrador, self::Activo], true);
    }

    /** Verbo de la acción que lleva a este estado. */
    public function actionLabel(self $from): string
    {
        return match ($this) {
            self::Activo => $from === self::Cerrado ? 'Reabrir' : 'Activar',
            self::Cerrado => 'Cerrar',
            self::Archivado => 'Archivar',
            self::Borrador => 'Regresar a borrador',
        };
    }
}
