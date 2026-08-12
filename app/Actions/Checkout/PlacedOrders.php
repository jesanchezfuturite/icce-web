<?php

namespace App\Actions\Checkout;

use App\Models\Order;

/**
 * Resultado de un checkout. Un carrito mixto produce dos órdenes: la parte con
 * existencia se cobra y la parte de volumen o bajo pedido entra como
 * cotización, cada una con su folio y su seguimiento propio.
 */
final readonly class PlacedOrders
{
    public function __construct(
        public ?Order $sale = null,
        public ?Order $quote = null,
    ) {}

    /** @return list<Order> */
    public function all(): array
    {
        return array_values(array_filter([$this->sale, $this->quote]));
    }

    public function isMixed(): bool
    {
        return $this->sale !== null && $this->quote !== null;
    }

    public function primary(): Order
    {
        return $this->sale ?? $this->quote;
    }
}
