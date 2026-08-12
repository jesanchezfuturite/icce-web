<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\PaymentResult;
use RuntimeException;

/**
 * Esqueleto de Openpay (REQ-03).
 *
 * Sin implementar hasta contar con merchant id y llaves de ICCE. Openpay es
 * la opción con mejor cobertura de SPEI y tiendas en México; al implementarlo:
 *
 *   1. composer require openpay/sdk
 *   2. Cargo con token de tarjeta, o charge tipo `bank_account` para SPEI.
 *   3. SPEI devuelve CLABE y referencia: van en PaymentResult::pending().
 *   4. Confirmar contra el webhook charge.succeeded.
 */
class OpenpayGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'openpay';
    }

    public function charge(Order $order, array $payload = []): PaymentResult
    {
        throw new RuntimeException(
            'La pasarela Openpay aún no está implementada. '
            .'Configura ICCE_PAYMENT_DRIVER=simulado o completa OpenpayGateway.',
        );
    }

    public function supportedMethods(): array
    {
        return [
            'card' => 'Tarjeta de crédito o débito',
            'spei' => 'Transferencia SPEI',
            'store' => 'Pago en tienda',
        ];
    }
}
