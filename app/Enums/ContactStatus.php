<?php

namespace App\Enums;

enum ContactStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Answered = 'answered';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Sin atender',
            self::InProgress => 'En proceso',
            self::Answered => 'Respondido',
            self::Closed => 'Cerrado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'danger',
            self::InProgress => 'warning',
            self::Answered => 'success',
            self::Closed => 'gray',
        };
    }
}
