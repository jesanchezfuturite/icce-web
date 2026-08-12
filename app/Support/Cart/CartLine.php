<?php

namespace App\Support\Cart;

use App\Enums\PurchaseMode;
use App\Models\Product;

/**
 * Una línea del carrito ya resuelta contra el estado actual del producto.
 *
 * El modo no se guarda en sesión: se recalcula en cada lectura. Si mientras el
 * cliente decide se agota la existencia o el producto pasa a "bajo pedido", la
 * línea se mueve sola a cotización en vez de prometer un cobro imposible.
 */
final readonly class CartLine
{
    public function __construct(
        public Product $product,
        public int $quantity,
        public PurchaseMode $mode,
    ) {}

    public static function for(Product $product, int $quantity): self
    {
        return new self($product, $quantity, $product->purchaseModeFor($quantity));
    }

    public function unitPrice(): float
    {
        return (float) $this->product->price;
    }

    public function total(): float
    {
        return round($this->unitPrice() * $this->quantity, 2);
    }

    public function isPurchasable(): bool
    {
        return $this->mode === PurchaseMode::Buy;
    }

    /** Por qué esta línea no se puede cobrar en línea; null si sí se puede. */
    public function quoteReason(): ?string
    {
        if ($this->isPurchasable()) {
            return null;
        }

        return match (true) {
            ! $this->product->is_for_sale => 'Equipo de renta: se cotiza según periodo y ubicación.',
            $this->product->is_on_demand => 'Producto bajo pedido: se confirma tiempo de entrega.',
            $this->quantity > $this->product->max_direct_purchase => sprintf(
                'Arriba de %d %s aplica precio de proyecto.',
                $this->product->max_direct_purchase,
                str($this->product->unit)->plural($this->product->max_direct_purchase),
            ),
            $this->quantity > $this->product->stock_qty => sprintf(
                'Sólo hay %d en existencia; el resto se surte bajo pedido.',
                max($this->product->stock_qty, 0),
            ),
            default => 'Requiere confirmación de un agente.',
        };
    }
}
