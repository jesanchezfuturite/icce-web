<?php

namespace App\Actions\Checkout;

final readonly class CheckoutData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public ?string $company = null,
        public ?string $rfc = null,
        /** @var array<string, string> */
        public array $shippingAddress = [],
        /** @var array<string, string>|null */
        public ?array $billingAddress = null,
        public ?string $notes = null,
        public string $paymentMethod = 'card',
        /** @var array<string, mixed> */
        public array $paymentPayload = [],
    ) {}

    public static function fromRequest(array $validated): self
    {
        $address = array_filter([
            'calle' => $validated['calle'] ?? null,
            'colonia' => $validated['colonia'] ?? null,
            'ciudad' => $validated['ciudad'] ?? null,
            'estado' => $validated['estado'] ?? null,
            'cp' => $validated['cp'] ?? null,
            'referencias' => $validated['referencias'] ?? null,
        ]);

        return new self(
            name: $validated['nombre'],
            email: $validated['email'],
            phone: $validated['telefono'],
            company: $validated['empresa'] ?? null,
            rfc: $validated['rfc'] ?? null,
            shippingAddress: $address,
            billingAddress: null,
            notes: $validated['notas'] ?? null,
            paymentMethod: $validated['metodo_pago'] ?? 'card',
            paymentPayload: [
                'method' => $validated['metodo_pago'] ?? 'card',
                'card_number' => preg_replace('/\s+/', '', $validated['tarjeta'] ?? ''),
            ],
        );
    }
}
