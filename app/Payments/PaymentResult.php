<?php

namespace App\Payments;

final readonly class PaymentResult
{
    private function __construct(
        public bool $successful,
        public ?string $reference = null,
        public ?string $status = null,
        public ?string $message = null,
        /** URL a la que hay que enviar al cliente (3-D Secure, SPEI, etc.). */
        public ?string $redirectUrl = null,
    ) {}

    public static function paid(string $reference, ?string $message = null): self
    {
        return new self(true, $reference, 'paid', $message);
    }

    /** Cobro iniciado pero aún no confirmado: SPEI, OXXO, 3-D Secure. */
    public static function pending(string $reference, ?string $redirectUrl = null, ?string $message = null): self
    {
        return new self(true, $reference, 'pending', $message, $redirectUrl);
    }

    public static function failed(string $message): self
    {
        return new self(false, null, 'failed', $message);
    }
}
