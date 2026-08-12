<?php

use App\Support\Cart\Cart;
use Livewire\Attributes\On;
use Livewire\Component;

/** Indicador del encabezado; escucha los cambios de cualquier otro componente. */
new class extends Component
{
    public int $count = 0;

    public function mount(Cart $cart): void
    {
        $this->count = $cart->count();
    }

    #[On('carrito-actualizado')]
    public function refresh(Cart $cart): void
    {
        $this->count = $cart->count();
    }
};
?>

<a href="{{ route('carrito') }}"
   class="relative rounded-full p-2.5 text-white/70 transition hover:bg-white/10 hover:text-white"
   aria-label="Carrito y cotización{{ $count > 0 ? ": {$count} artículos" : '' }}">
    <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
        <path d="M2.5 3h2l1.7 8.5a1.5 1.5 0 0 0 1.5 1.2h6.4a1.5 1.5 0 0 0 1.5-1.2L17 6H5.5" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="8" cy="16.5" r="1.2" fill="currentColor" stroke="none"/>
        <circle cx="14.5" cy="16.5" r="1.2" fill="currentColor" stroke="none"/>
    </svg>

    @if($count > 0)
        <span class="absolute -right-0.5 -top-0.5 flex min-w-5 items-center justify-center rounded-full bg-brand-500 px-1 text-[0.6875rem] font-bold text-carbon-950">
            {{ $count > 99 ? '99+' : $count }}
        </span>
    @endif
</a>
