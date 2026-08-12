<?php

namespace App\Enums;

enum RentalRequestStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Quoted = 'quoted';
    case Won = 'won';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nueva',
            self::Contacted => 'Contactado',
            self::Quoted => 'Cotizado',
            self::Won => 'Ganado',
            self::Closed => 'Cerrado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'danger',
            self::Contacted => 'warning',
            self::Quoted => 'info',
            self::Won => 'success',
            self::Closed => 'gray',
        };
    }
}
