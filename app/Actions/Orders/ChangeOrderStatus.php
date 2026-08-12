<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Mail\OrderStatusChangedMail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Único punto por el que cambia el estatus de una orden (REQ-05).
 *
 * Concentra tres cosas que deben ocurrir juntas o no ocurrir: la marca de
 * tiempo del hito, el asiento en la bitácora que alimenta el timeline del
 * cliente (REQ-04) y el aviso por correo. Si el estatus se cambiara sueltamente
 * con un `update()`, el timeline del portal quedaría mintiendo.
 */
class ChangeOrderStatus
{
    public function __invoke(
        Order $order,
        OrderStatus $to,
        ?User $author = null,
        ?string $note = null,
        bool $notifyCustomer = true,
        ?string $estimatedDeliveryDate = null,
        ?string $trackingNumber = null,
        ?string $carrier = null,
    ): Order {
        $from = $order->status;

        if ($from === $to && $estimatedDeliveryDate === null && $trackingNumber === null) {
            return $order;
        }

        DB::transaction(function () use ($order, $from, $to, $author, $note, $notifyCustomer, $estimatedDeliveryDate, $trackingNumber, $carrier) {
            $order->fill(array_filter([
                'estimated_delivery_date' => $estimatedDeliveryDate,
                'tracking_number' => $trackingNumber,
                'carrier' => $carrier,
            ], fn ($value) => $value !== null && $value !== ''));

            $order->status = $to;

            // Cada hito sella su propia fecha; no se recalculan desde el log
            match ($to) {
                OrderStatus::Quoted => $order->quoted_at ??= now(),
                OrderStatus::Paid => $order->paid_at ??= now(),
                OrderStatus::Shipped => $order->shipped_at ??= now(),
                OrderStatus::Delivered => $order->delivered_at ??= now(),
                OrderStatus::Cancelled => $order->cancelled_at ??= now(),
                default => null,
            };

            $order->save();

            if ($from !== $to) {
                $order->statusHistories()->create([
                    'user_id' => $author?->id,
                    'from_status' => $from,
                    'to_status' => $to,
                    'note' => $note,
                    'notified_customer' => $notifyCustomer,
                ]);
            }
        });

        if ($notifyCustomer && $from !== $to) {
            $this->notify($order, $from, $note);
        }

        return $order->refresh();
    }

    /** Un fallo de correo no debe revertir un cambio de estatus ya asentado. */
    private function notify(Order $order, OrderStatus $from, ?string $note): void
    {
        try {
            Mail::to($order->customer_email)->send(new OrderStatusChangedMail($order, $from, $note));
        } catch (\Throwable $e) {
            Log::error('No se pudo avisar el cambio de estatus de '.$order->folio, ['error' => $e->getMessage()]);
        }
    }
}
