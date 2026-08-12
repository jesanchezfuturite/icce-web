<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\PaymentResult;
use RuntimeException;

/**
 * Esqueleto de Stripe (REQ-03).
 *
 * Deliberadamente sin implementar: conectarlo requiere las llaves de la cuenta
 * de ICCE y el secreto del webhook. Falla ruidosamente en vez de fingir un
 * cobro. Al implementarlo:
 *
 *   1. composer require stripe/stripe-php
 *   2. Crear un PaymentIntent por el total en centavos, moneda MXN.
 *   3. Devolver PaymentResult::pending() con el client_secret para 3-D Secure.
 *   4. Confirmar en el webhook payment_intent.succeeded, no aquí: el cliente
 *      puede cerrar el navegador entre la autorización y la confirmación.
 */
class StripeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'stripe';
    }

    public function charge(Order $order, array $payload = []): PaymentResult
    {
        throw new RuntimeException(
            'La pasarela Stripe aún no está implementada. '
            .'Configura ICCE_PAYMENT_DRIVER=simulado o completa StripeGateway.',
        );
    }

    public function supportedMethods(): array
    {
        return [
            'card' => 'Tarjeta de crédito o débito',
            'spei' => 'Transferencia SPEI',
        ];
    }
}
