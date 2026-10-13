<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Registra un pago hecho fuera de la plataforma (transferencia, efectivo,
 * depósito…) y pasa la orden directo a «Pagado», sin exigir que antes haya
 * estado «Pendiente de pago». También confirma un SPEI que la pasarela dejó
 * pendiente. Pasa por ChangeOrderStatus: bitácora, fecha y aviso van juntos.
 */
class RegisterOfflinePayment
{
    public const METHODS = [
        'transferencia' => 'Transferencia bancaria',
        'efectivo' => 'Efectivo',
        'deposito' => 'Depósito en ventanilla',
        'cheque' => 'Cheque',
        'otro' => 'Otro',
    ];

    public function __construct(private readonly ChangeOrderStatus $changeStatus) {}

    public function __invoke(
        Order $order,
        string $method,
        ?string $reference = null,
        ?User $author = null,
        ?string $note = null,
        bool $notifyCustomer = true,
    ): Order {
        if (! in_array($order->status, OrderStatus::unpaid(), strict: true)) {
            throw new DomainException('Sólo se registra el pago de una orden que aún no está pagada.');
        }

        $label = self::METHODS[$method] ?? $method;

        return DB::transaction(function () use ($order, $reference, $author, $note, $notifyCustomer, $label) {
            $order->update([
                'payment_provider' => 'manual',
                'payment_reference' => trim($label.' '.($reference ?? '')),
                'payment_status' => 'paid',
            ]);

            return ($this->changeStatus)(
                order: $order,
                to: OrderStatus::Paid,
                author: $author,
                note: $note ?: "Pago recibido fuera de la plataforma ({$label}).",
                notifyCustomer: $notifyCustomer,
            );
        });
    }
}
