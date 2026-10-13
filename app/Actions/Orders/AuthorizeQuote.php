<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\User;
use DomainException;

/**
 * Autoriza una cotización ya enviada y habilita el pago desde el portal.
 *
 * El precio y el descuento pactados viven en la propia orden, así que lo que el
 * cliente paga es exactamente lo autorizado: no puede cambiar cantidades ni
 * reutilizar el descuento en otra compra. La vigencia (`quote_valid_until`) es
 * la fecha límite para pagar.
 */
class AuthorizeQuote
{
    public function __construct(private readonly ChangeOrderStatus $changeStatus) {}

    public function __invoke(
        Order $order,
        ?User $author = null,
        ?string $validUntil = null,
        ?string $note = null,
        bool $notifyCustomer = true,
    ): Order {
        if ($order->order_type !== OrderType::Quote || $order->status !== OrderStatus::Quoted) {
            throw new DomainException('Sólo se puede habilitar el pago de una cotización que ya fue enviada al cliente.');
        }

        if ($order->paid_at !== null || $order->payment_status === 'paid') {
            throw new DomainException('Esta cotización ya fue pagada.');
        }

        if ((float) $order->total_amount <= 0) {
            throw new DomainException('La cotización no tiene un total por cobrar.');
        }

        if ($validUntil) {
            $order->update(['quote_valid_until' => $validUntil]);
        }

        return ($this->changeStatus)(
            order: $order,
            to: OrderStatus::PendingPayment,
            author: $author,
            note: $note ?: 'Cotización autorizada. Ya puedes pagarla desde tu cuenta.',
            notifyCustomer: $notifyCustomer,
        );
    }
}
