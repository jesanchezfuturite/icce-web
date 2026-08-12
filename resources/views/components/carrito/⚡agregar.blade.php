<?php

use App\Enums\PurchaseMode;
use App\Models\Product;
use App\Support\Cart\Cart;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Control de alta al carrito en la ficha de producto.
 *
 * La cantidad recalcula el modo en vivo: subir de 10 a 11 unidades cambia el
 * botón de "Agregar al carrito" a "Agregar a cotización" antes de que el
 * cliente lo presione, en vez de sorprenderlo en el checkout (REQ-01/02).
 */
new class extends Component
{
    public Product $product;

    public int $quantity = 1;

    public function mount(Product $product): void
    {
        $this->product = $product;
    }

    #[Computed]
    public function mode(): PurchaseMode
    {
        return $this->product->purchaseModeFor(max($this->quantity, 1));
    }

    public function updatedQuantity(): void
    {
        $this->quantity = max(1, min((int) $this->quantity, 9999));
    }

    public function add(Cart $cart): void
    {
        $cart->add($this->product, max(1, (int) $this->quantity));

        $this->dispatch('carrito-actualizado');
        $this->dispatch('agregado', nombre: $this->product->name);
    }
};
?>

<div x-data="{ agregado: false }" @agregado.window="agregado = true; setTimeout(() => agregado = false, 3500)">
    {{-- Aviso de qué va a pasar, antes de presionar --}}
    <div @class([
        'rounded-xl border p-5 transition',
        'border-brand-500/40 bg-brand-50' => $this->mode() === PurchaseMode::Buy,
        'border-sky-300 bg-sky-50' => $this->mode() === PurchaseMode::Quote,
    ])>
        <p class="font-display text-sm font-bold text-carbon-950">{{ $this->mode()->label() }}</p>
        <p class="mt-1.5 text-sm leading-relaxed text-carbon-600">
            @if($this->mode() === PurchaseMode::Buy)
                Hasta {{ $product->max_direct_purchase }} {{ Str::plural($product->unit, $product->max_direct_purchase) }}
                se cobran en línea. Arriba de esa cantidad el pedido pasa a cotización con precio de proyecto.
            @elseif(! $product->is_for_sale)
                Equipo de renta: la tarifa depende del periodo y la ubicación de la obra.
            @elseif($product->is_on_demand)
                Este producto se surte bajo pedido: se cotiza con tiempo de entrega confirmado.
            @elseif($quantity > $product->stock_qty)
                Pediste {{ $quantity }} y hay {{ max($product->stock_qty, 0) }} en existencia.
                El pedido entra como cotización y confirmamos el resto.
            @else
                Requiere confirmación de un agente antes de cobrarse.
            @endif
        </p>
    </div>

    <div class="mt-6 flex flex-wrap items-center gap-3">
        <div class="flex h-12 items-center rounded-full border border-carbon-300">
            <button type="button" wire:click="$set('quantity', {{ max(1, $quantity - 1) }})"
                    class="flex size-11 items-center justify-center rounded-full text-carbon-500 transition hover:text-carbon-950"
                    aria-label="Disminuir cantidad" @disabled($quantity <= 1)>
                <svg class="size-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M3.5 8h9" stroke-linecap="round"/>
                </svg>
            </button>

            <input type="number" min="1" wire:model.live.debounce.400ms="quantity"
                   class="h-full w-14 border-0 bg-transparent p-0 text-center text-sm font-semibold text-carbon-950 outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                   aria-label="Cantidad">

            <button type="button" wire:click="$set('quantity', {{ $quantity + 1 }})"
                    class="flex size-11 items-center justify-center rounded-full text-carbon-500 transition hover:text-carbon-950"
                    aria-label="Aumentar cantidad">
                <svg class="size-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M8 3.5v9M3.5 8h9" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <x-ui.button wire:click="add" type="button" size="lg" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="add">{{ $this->mode()->ctaLabel() }}</span>
            <span wire:loading wire:target="add">Agregando…</span>
        </x-ui.button>
    </div>

    <p x-show="agregado" x-cloak x-transition
       class="mt-4 flex items-center gap-2 text-sm font-semibold text-brand-700">
        <svg class="size-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="m3.5 8.5 3 3 6-7" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Agregado.
        <a href="{{ route('carrito') }}" class="underline">Ver carrito</a>
    </p>
</div>
