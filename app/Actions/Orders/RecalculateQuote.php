<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Support\QuoteTotals;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula y guarda los importes de una cotización a partir de los precios que
 * dejó el agente en cada partida (`quoted_unit_price`, o el de lista si no hay).
 *
 * El precio de lista nunca se sobrescribe: la orden conserva ambos para poder
 * auditar cuánto descuento se dio. Lo llaman tanto «Guardar» en el detalle de la
 * orden como el envío de la cotización, así lo guardado siempre coincide con lo
 * que el agente vio en pantalla (ambos usan QuoteTotals).
 */
class RecalculateQuote
{
    public function __invoke(Order $order, mixed $discount = null): QuoteTotals
    {
        return DB::transaction(function () use ($order, $discount) {
            $items = $order->items()->get();

            foreach ($items as $item) {
                $item->update([
                    'line_total' => QuoteTotals::lineTotal(
                        $item->quantity,
                        QuoteTotals::effectivePrice($item->quoted_unit_price, $item->unit_price),
                    ),
                ]);
            }

            $totals = QuoteTotals::calculate(
                $items->map(fn ($item) => [
                    $item->quantity,
                    QuoteTotals::effectivePrice($item->quoted_unit_price, $item->unit_price),
                ]),
                $discount ?? $order->discount_amount,
            );

            $order->update([
                'subtotal' => $totals->subtotal,
                'discount_amount' => $totals->discount,
                'tax_amount' => $totals->tax,
                'total_amount' => $totals->total,
            ]);

            return $totals;
        });
    }
}
