<?php

namespace App\Enums;

/**
 * Cobertura del equipo en renta (4.1 / 4.2). Determina qué campos pide el
 * formulario adaptativo de solicitud (REQ-07).
 */
enum RentalCoverage: string
{
    case National = 'national';
    case Local = 'local';

    public function label(): string
    {
        return match ($this) {
            self::National => 'Cobertura nacional',
            self::Local => 'Cobertura local (Monterrey)',
        };
    }
}
