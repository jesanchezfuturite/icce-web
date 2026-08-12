<?php

namespace App\Enums;

enum OrderType: string
{
    case DirectSale = 'direct_sale';
    case Quote = 'quote';

    public function label(): string
    {
        return match ($this) {
            self::DirectSale => 'Venta directa',
            self::Quote => 'Cotización',
        };
    }

    /** Prefijo del folio visible para el cliente: VD-2026-00014 / COT-2026-00014. */
    public function folioPrefix(): string
    {
        return match ($this) {
            self::DirectSale => 'VD',
            self::Quote => 'COT',
        };
    }
}
