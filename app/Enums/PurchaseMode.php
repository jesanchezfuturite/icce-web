<?php

namespace App\Enums;

/**
 * Resultado del motor de decisión del carrito híbrido (3.3 / REQ-01 / REQ-02):
 * una línea se cobra en línea o se convierte en solicitud de cotización.
 */
enum PurchaseMode: string
{
    case Buy = 'buy';
    case Quote = 'quote';

    public function label(): string
    {
        return match ($this) {
            self::Buy => 'Compra directa',
            self::Quote => 'Solicitud de cotización',
        };
    }

    public function ctaLabel(): string
    {
        return match ($this) {
            self::Buy => 'Agregar al carrito',
            self::Quote => 'Agregar a cotización',
        };
    }
}
