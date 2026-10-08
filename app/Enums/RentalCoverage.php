<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Cobertura del equipo en renta (4.1 / 4.2). Determina qué campos pide el
 * formulario adaptativo de solicitud (REQ-07).
 */
enum RentalCoverage: string implements HasLabel
{
    case National = 'national';
    case Local = 'local';

    /** Etiqueta que Filament muestra en selectores y filtros. */
    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match ($this) {
            self::National => 'Cobertura en toda la República',
            self::Local => 'Solo cobertura local (Monterrey)',
        };
    }
}
