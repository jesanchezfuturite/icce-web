<?php

namespace App\Payments\Contracts;

use App\Models\Order;
use App\Payments\PaymentResult;

/**
 * Frontera con la pasarela de pagos (REQ-03).
 *
 * El checkout depende de esta interfaz y no de Stripe ni de Openpay, así que
 * cambiar de proveedor —o correr sin ninguno en local— no toca el flujo de
 * compra. Las implementaciones viven en App\Payments\Gateways.
 */
interface PaymentGateway
{
    /** Identificador que se guarda en `orders.payment_provider`. */
    public function name(): string;

    /**
     * Intenta cobrar una orden.
     *
     * @param  array<string, mixed>  $payload  Datos del método de pago
     */
    public function charge(Order $order, array $payload = []): PaymentResult;

    /** Métodos ofrecidos al cliente en el checkout. */
    public function supportedMethods(): array;
}
