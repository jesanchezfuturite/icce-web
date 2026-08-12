<?php

use App\Support\Cart\Cart;
use App\Support\Cart\CartLine;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Carrito / cotizador (3.4).
 *
 * Presenta las dos naturalezas por separado para que el cliente vea, antes de
 * llegar al checkout, qué se le va a cobrar hoy y qué le va a cotizar un agente.
 */
new class extends Component
{
    /** @var array<int, int> producto => cantidad, para los inputs */
    public array $quantities = [];

    public function mount(Cart $cart): void
    {
        $this->syncQuantities($cart);
    }

    private function syncQuantities(Cart $cart): void
    {
        $this->quantities = $cart->lines()
            ->mapWithKeys(fn (CartLine $line) => [$line->product->id => $line->quantity])
            ->all();
    }

    public function updateQuantity(Cart $cart, int $productId, int $quantity): void
    {
        $cart->setQuantity($productId, max(0, $quantity));

        $this->syncQuantities($cart);
        $this->dispatch('carrito-actualizado');
    }

    public function remove(Cart $cart, int $productId): void
    {
        $cart->remove($productId);

        $this->syncQuantities($cart);
        $this->dispatch('carrito-actualizado');
    }

    public function clear(Cart $cart): void
    {
        $cart->clear();

        $this->quantities = [];
        $this->dispatch('carrito-actualizado');
    }

    #[Computed]
    public function cart(): Cart
    {
        return app(Cart::class);
    }

    #[Computed]
    public function purchasable(): Collection
    {
        return $this->cart()->purchasable();
    }

    #[Computed]
    public function quotable(): Collection
    {
        return $this->cart()->quotable();
    }
};
?>

<div>
    @if($this->cart()->isEmpty())
        <div class="rounded-2xl border border-carbon-200 bg-carbon-50 p-12 text-center">
            <p class="font-display text-xl font-extrabold text-carbon-950">Tu carrito está vacío</p>
            <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-carbon-600">
                Explora el catálogo y agrega lo que necesites. Puedes mezclar material con
                existencia y material de volumen: cada parte sigue su camino.
            </p>
            <x-ui.button href="{{ route('catalogo.index') }}" class="mt-7">Ir al catálogo</x-ui.button>
        </div>
    @else
        <div class="grid gap-10 lg:grid-cols-[1fr_22rem] lg:gap-12">
            <div class="space-y-10">
                {{-- Compra directa --}}
                @if($this->purchasable()->isNotEmpty())
                    <section>
                        <div class="flex items-center gap-3">
                            <span class="flex size-7 items-center justify-center rounded-full bg-brand-500 text-carbon-950">
                                <svg class="size-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                    <path d="m3.5 8.5 3 3 6-7" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <h2 class="font-display text-lg font-extrabold text-carbon-950">Se cobra en línea</h2>
                            <span class="text-sm text-carbon-500">{{ $this->purchasable()->count() }} {{ Str::plural('partida', $this->purchasable()->count()) }}</span>
                        </div>

                        <div class="mt-5 divide-y divide-carbon-200 border-y border-carbon-200">
                            @foreach($this->purchasable() as $line)
                                @include('partials.cart-line', ['line' => $line])
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Cotización --}}
                @if($this->quotable()->isNotEmpty())
                    <section>
                        <div class="flex items-center gap-3">
                            <span class="flex size-7 items-center justify-center rounded-full bg-sky-500 text-white">
                                <svg class="size-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M8 5v4M8 11.5v.01" stroke-linecap="round"/><circle cx="8" cy="8" r="6.5"/>
                                </svg>
                            </span>
                            <h2 class="font-display text-lg font-extrabold text-carbon-950">Pasa a cotización</h2>
                            <span class="text-sm text-carbon-500">{{ $this->quotable()->count() }} {{ Str::plural('partida', $this->quotable()->count()) }}</span>
                        </div>

                        <p class="mt-3 text-sm leading-relaxed text-carbon-600">
                            Un agente revisa estas partidas, aplica el descuento por volumen que corresponda
                            y te envía la propuesta con enlace de pago. No se cobra nada hoy.
                        </p>

                        <div class="mt-5 divide-y divide-carbon-200 border-y border-carbon-200">
                            @foreach($this->quotable() as $line)
                                @include('partials.cart-line', ['line' => $line])
                            @endforeach
                        </div>
                    </section>
                @endif

                <button type="button" wire:click="clear"
                        wire:confirm="¿Vaciar el carrito por completo?"
                        class="text-sm font-semibold text-carbon-500 underline transition hover:text-carbon-900">
                    Vaciar carrito
                </button>
            </div>

            {{-- Resumen --}}
            <aside class="lg:sticky lg:top-32 lg:self-start">
                <div class="rounded-2xl border border-carbon-200 p-6">
                    <h2 class="font-display text-sm font-bold uppercase tracking-[0.16em] text-carbon-950">Resumen</h2>

                    <dl class="mt-5 space-y-3 text-sm">
                        @if($this->purchasable()->isNotEmpty())
                            <div class="flex justify-between">
                                <dt class="text-carbon-500">Subtotal a pagar</dt>
                                <dd class="tabular-nums text-carbon-900">
                                    ${{ number_format($this->cart()->subtotal($this->purchasable()), 2) }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-carbon-500">IVA</dt>
                                <dd class="tabular-nums text-carbon-900">
                                    ${{ number_format($this->cart()->tax($this->purchasable()), 2) }}
                                </dd>
                            </div>
                            <div class="flex justify-between border-t border-carbon-200 pt-3">
                                <dt class="font-display font-bold text-carbon-950">Total hoy</dt>
                                <dd class="font-display text-lg font-extrabold tabular-nums text-carbon-950">
                                    ${{ number_format($this->cart()->total($this->purchasable()), 2) }}
                                </dd>
                            </div>
                        @endif

                        @if($this->quotable()->isNotEmpty())
                            <div class="flex justify-between rounded-lg bg-sky-50 px-3 py-2.5 @if($this->purchasable()->isNotEmpty()) mt-4 @endif">
                                <dt class="text-sky-800">Por cotizar (estimado)</dt>
                                <dd class="tabular-nums font-semibold text-sky-900">
                                    ${{ number_format($this->cart()->total($this->quotable()), 2) }}
                                </dd>
                            </div>
                        @endif
                    </dl>

                    <x-ui.button href="{{ route('checkout') }}" size="lg" class="mt-6 w-full">
                        @if($this->purchasable()->isEmpty())
                            Enviar solicitud de cotización
                        @elseif($this->quotable()->isEmpty())
                            Continuar al pago
                        @else
                            Pagar y cotizar
                        @endif
                    </x-ui.button>

                    <p class="mt-4 text-xs leading-relaxed text-carbon-500">
                        Precios en MXN antes de envío. La cotización no genera cobro.
                    </p>
                </div>

                <a href="{{ route('catalogo.index') }}"
                   class="mt-4 block text-center text-sm font-semibold text-brand-700 underline transition hover:text-brand-800">
                    Seguir comprando
                </a>
            </aside>
        </div>
    @endif
</div>
