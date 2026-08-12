<?php

namespace App\Actions\Checkout;

use RuntimeException;

/**
 * Entre que el cliente armó el carrito y confirmó, la existencia cambió.
 * Lleva los nombres afectados para poder decírselo con precisión.
 */
class OutOfStockException extends RuntimeException
{
    /** @param list<string> $products */
    public function __construct(public readonly array $products)
    {
        parent::__construct('La existencia cambió mientras confirmabas el pedido.');
    }

    public function describe(): string
    {
        return count($this->products) === 1
            ? sprintf('«%s» ya no tiene la existencia que pediste.', $this->products[0])
            : sprintf('Estos productos ya no tienen la existencia que pediste: %s.', implode(', ', $this->products));
    }
}
