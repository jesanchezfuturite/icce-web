<?php

namespace App\Actions\Checkout;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Cart\Cart;
use App\Support\Cart\CartLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Convierte el carrito en órdenes (flujos 1 y 2 del AppFlow).
 *
 * El carrito puede contener las dos naturalezas a la vez, y forzar todo a un
 * solo camino sería peor para ambas partes: el cliente esperaría días por una
 * llana que estaba en almacén, o ICCE cobraría en línea un pedido de volumen
 * sin margen de negociación. Por eso un carrito mixto produce dos órdenes.
 *
 * La existencia se aparta al crear la orden de venta, dentro de la misma
 * transacción y con bloqueo, para que dos checkouts simultáneos no vendan la
 * misma pieza. Si el cobro falla después, `releaseStock()` la devuelve.
 */
class PlaceOrders
{
    public function __invoke(Cart $cart, CheckoutData $data, ?User $user = null): PlacedOrders
    {
        $purchasable = $cart->purchasable();
        $quotable = $cart->quotable();

        return DB::transaction(function () use ($purchasable, $quotable, $data, $user) {
            $sale = $purchasable->isNotEmpty()
                ? $this->createOrder(OrderType::DirectSale, $purchasable, $data, $user)
                : null;

            $quote = $quotable->isNotEmpty()
                ? $this->createOrder(OrderType::Quote, $quotable, $data, $user)
                : null;

            return new PlacedOrders($sale, $quote);
        });
    }

    /** @param Collection<int, CartLine> $lines */
    private function createOrder(
        OrderType $type,
        Collection $lines,
        CheckoutData $data,
        ?User $user,
    ): Order {
        if ($type === OrderType::DirectSale) {
            $this->reserveStock($lines);
        }

        $subtotal = round($lines->sum(fn (CartLine $line) => $line->total()), 2);
        $tax = round($subtotal * (float) config('icce.tax_rate'), 2);

        $order = Order::create([
            'folio' => Order::nextFolio($type),
            'user_id' => $user?->id,
            'order_type' => $type,
            // La venta nace pendiente y sólo pasa a pagada cuando la pasarela
            // confirma; la cotización espera a que un agente la trabaje.
            'status' => OrderStatus::Pending,
            'customer_name' => $data->name,
            'customer_email' => $data->email,
            'customer_phone' => $data->phone,
            'customer_company' => $data->company,
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total_amount' => round($subtotal + $tax, 2),
            'currency' => 'MXN',
            'shipping_address' => $data->shippingAddress ?: null,
            'billing_address' => $data->billingAddress,
            'customer_notes' => $data->notes,
            'quote_valid_until' => $type === OrderType::Quote ? now()->addDays(15) : null,
        ]);

        foreach ($lines as $line) {
            $order->items()->create([
                'product_id' => $line->product->id,
                // Instantánea: la orden histórica no debe cambiar si el catálogo sí
                'product_sku' => $line->product->sku,
                'product_name' => $line->product->name,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice(),
                'line_total' => $line->total(),
                'notes' => $line->quoteReason(),
            ]);
        }

        return $order;
    }

    /**
     * Aparta existencia con bloqueo pesimista. Si alguna línea ya no alcanza,
     * la transacción completa se revierte.
     *
     * @param  Collection<int, CartLine>  $lines
     */
    private function reserveStock(Collection $lines): void
    {
        $products = Product::whereIn('id', $lines->pluck('product.id'))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $insufficient = [];

        foreach ($lines as $line) {
            $fresh = $products->get($line->product->id);

            if ($fresh === null || $fresh->stock_qty < $line->quantity) {
                $insufficient[] = $line->product->name;
            }
        }

        if ($insufficient !== []) {
            throw new OutOfStockException($insufficient);
        }

        foreach ($lines as $line) {
            $products->get($line->product->id)->decrement('stock_qty', $line->quantity);
        }
    }

    /** Devuelve al inventario lo apartado por una orden que no prosperó. */
    public function releaseStock(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items()->whereNotNull('product_id')->get() as $item) {
                Product::whereKey($item->product_id)->increment('stock_qty', $item->quantity);
            }
        });
    }
}
