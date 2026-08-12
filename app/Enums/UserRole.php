<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Sales = 'sales';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Sales => 'Ventas',
            self::Client => 'Cliente',
        };
    }

    /** Roles con acceso al backoffice (Filament). */
    public function canAccessAdminPanel(): bool
    {
        return $this !== self::Client;
    }
}
