<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\PaymentResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * El cliente paga desde su portal una cotización que el agente autorizó.
 *
 * Es de una sola vez: la orden se bloquea, se revalida `canBePaidOnline()` y el
 * cobro sólo se intenta mientras siga «Pendiente de pago». Un segundo clic, una
 * pestaña repetida o dos dispositivos a la vez no pueden cobrar dos veces, y
 * una orden ya pagada deja de aceptar pagos. Un rechazo no consume la
 * autorización: la orden sigue pendiente para reintentar.
 */
class PayQuote
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ChangeOrderStatus $changeStatus,
    ) {}

    /** @param array<string, mixed> $payload Datos del método de pago */
    public function __invoke(Order $order, array $payload, ?User $customer = null): PaymentResult
    {
        return DB::transaction(function () use ($order, $payload, $customer) {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->canBePaidOnline()) {
                return PaymentResult::failed($this->reasonFor($locked));
            }

            try {
                $result = $this->gateway->charge($locked, $payload);
            } catch (\Throwable $e) {
                Log::error('La pasarela falló al cobrar '.$locked->folio, ['error' => $e->getMessage()]);

                $result = PaymentResult::failed('No pudimos procesar el pago en este momento. Inténtalo de nuevo en unos minutos o contacta a tu agente.');
            }

            if (! $result->successful) {
                $locked->update([
                    'payment_provider' => $this->gateway->name(),
                    'payment_status' => 'failed',
                ]);

                return $result;
            }

            $paid = $result->status === 'paid';

            $locked->update([
                'payment_provider' => $this->gateway->name(),
                'payment_reference' => $result->reference,
                'payment_status' => $result->status,
            ]);

            // SPEI y similares: queda registrado y bloqueado para nuevos intentos;
            // el agente confirma el depósito con «Registrar pago».
            if ($paid) {
                ($this->changeStatus)(
                    order: $locked,
                    to: OrderStatus::Paid,
                    author: $customer,
                    note: 'Pago de la cotización confirmado por la pasarela ('.$this->gateway->name().').',
                );
            }

            return $result;
        });
    }

    private function reasonFor(Order $order): string
    {
        return match (true) {
            $order->status === OrderStatus::Paid || $order->paid_at !== null => 'Esta cotización ya fue pagada.',
            $order->quoteHasExpired() => 'La vigencia de esta cotización terminó. Pide a tu agente que la renueve.',
            $order->payment_status === 'pending' => 'Ya registramos tu transferencia; en cuanto se acredite avanzamos tu pedido.',
            default => 'Esta cotización todavía no está habilitada para pago.',
        };
    }
}
