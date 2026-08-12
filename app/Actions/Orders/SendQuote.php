<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Mail\QuoteSentMail;
use App\Models\Order;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Envía la cotización ajustada al cliente (REQ-09).
 *
 * Recalcula los totales desde el precio que el agente dejó en cada partida
 * —`quoted_unit_price` cuando existe, el de lista si no— y adjunta el PDF.
 * El precio de lista nunca se sobrescribe: la orden conserva ambos para poder
 * auditar cuánto descuento se dio.
 */
class SendQuote
{
    public function __construct(private readonly ChangeOrderStatus $changeStatus) {}

    public function __invoke(
        Order $order,
        ?User $author = null,
        ?string $message = null,
        ?string $validUntil = null,
        float $discountAmount = 0,
    ): Order {
        $order->loadMissing('items');

        DB::transaction(function () use ($order, $discountAmount, $validUntil) {
            $subtotal = 0.0;

            foreach ($order->items as $item) {
                $unit = (float) ($item->quoted_unit_price ?? $item->unit_price);
                $lineTotal = round($unit * $item->quantity, 2);

                $item->update(['line_total' => $lineTotal]);
                $subtotal += $lineTotal;
            }

            $subtotal = round($subtotal, 2);
            $discount = min(round($discountAmount, 2), $subtotal);
            $taxable = $subtotal - $discount;

            $order->update([
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'tax_amount' => round($taxable * (float) config('icce.tax_rate'), 2),
                'total_amount' => round($taxable * (1 + (float) config('icce.tax_rate')), 2),
                'quote_valid_until' => $validUntil ?: now()->addDays(15),
            ]);
        });

        $order->refresh()->load('items');

        // El aviso lo manda esta acción con el PDF adjunto, no el genérico
        ($this->changeStatus)(
            order: $order,
            to: OrderStatus::Quoted,
            author: $author,
            note: $message ?: 'Cotización ajustada y enviada al cliente.',
            notifyCustomer: false,
        );

        $this->deliver($order, $message);

        return $order->refresh();
    }

    public function pdf(Order $order): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.quote', ['order' => $order->loadMissing('items', 'agent')])
            ->setPaper('letter');
    }

    private function deliver(Order $order, ?string $message): void
    {
        try {
            Mail::to($order->customer_email)->send(new QuoteSentMail($order, $message));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar la cotización '.$order->folio, ['error' => $e->getMessage()]);
        }
    }
}
