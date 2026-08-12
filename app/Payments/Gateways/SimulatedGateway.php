<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\PaymentResult;
use Illuminate\Support\Str;

/**
 * Pasarela simulada para desarrollo y pruebas.
 *
 * Permite recorrer el checkout completo sin credenciales. Se niega a operar en
 * producción: un cobro fingido en vivo sería peor que no tener pasarela.
 */
class SimulatedGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'simulado';
    }

    public function charge(Order $order, array $payload = []): PaymentResult
    {
        if (app()->isProduction()) {
            return PaymentResult::failed('La pasarela simulada no puede operar en producción.');
        }

        // Tarjeta de prueba reservada para ejercitar el camino de rechazo
        if (($payload['card_number'] ?? null) === '4000000000000002') {
            return PaymentResult::failed('La tarjeta fue rechazada por el banco emisor.');
        }

        $reference = 'SIM-'.Str::upper(Str::random(12));

        // SPEI nunca confirma al instante, ni siquiera simulado
        if (($payload['method'] ?? 'card') === 'spei') {
            return PaymentResult::pending(
                $reference,
                message: 'Transferencia SPEI registrada. La orden avanza al confirmarse el depósito.',
            );
        }

        return PaymentResult::paid($reference, 'Pago simulado aprobado.');
    }

    public function supportedMethods(): array
    {
        return [
            'card' => 'Tarjeta de crédito o débito',
            'spei' => 'Transferencia SPEI',
        ];
    }
}
