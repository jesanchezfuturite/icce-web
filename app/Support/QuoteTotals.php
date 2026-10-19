<?php

namespace App\Support;

/**
 * Totales de una cotización: la única fórmula del sistema.
 *
 * La usan por igual lo que el agente ve en pantalla mientras teclea (detalle de
 * la orden y modal de envío), lo que se guarda en la orden y lo que recibe el
 * cliente. Si cada lugar redondeara a su manera, la pantalla diría un total y el
 * correo otro.
 *
 * Orden de las cuentas: importe de cada renglón (precio por cantidad, a
 * centavos) → subtotal → descuento (nunca mayor al subtotal) → IVA sobre lo que
 * queda → total.
 */
final readonly class QuoteTotals
{
    public function __construct(
        public float $subtotal,
        public float $discount,
        public float $taxable,
        public float $tax,
        public float $total,
    ) {}

    /**
     * @param  iterable<array{0: mixed, 1: mixed}>  $lines  Pares [cantidad, precio unitario vigente]
     */
    public static function calculate(iterable $lines, mixed $discount = 0, ?float $taxRate = null): self
    {
        $rate = $taxRate ?? (float) config('icce.tax_rate');

        $subtotal = 0.0;

        foreach ($lines as [$quantity, $unitPrice]) {
            $subtotal += self::lineTotal($quantity, $unitPrice);
        }

        $subtotal = round($subtotal, 2);
        $discount = min(max(round(self::number($discount), 2), 0), $subtotal);
        $taxable = round($subtotal - $discount, 2);

        return new self(
            subtotal: $subtotal,
            discount: $discount,
            taxable: $taxable,
            tax: round($taxable * $rate, 2),
            total: round($taxable * (1 + $rate), 2),
        );
    }

    /** Importe de un renglón; cantidad o precio vacíos o inválidos valen cero. */
    public static function lineTotal(mixed $quantity, mixed $unitPrice): float
    {
        return round(self::number($unitPrice) * self::number($quantity), 2);
    }

    /** Precio vigente: el cotizado si el agente lo capturó, si no el de lista. */
    public static function effectivePrice(mixed $quoted, mixed $list): float
    {
        return $quoted !== null && $quoted !== '' ? self::number($quoted) : self::number($list);
    }

    private static function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
