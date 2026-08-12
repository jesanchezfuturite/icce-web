@php
    /** @var \App\Support\Cart\CartLine $line */
    $product = $line->product;
    $url = $product->is_rental ? route('renta.equipo', $product) : route('producto', $product);
@endphp

<div class="flex flex-wrap items-start gap-4 py-5" wire:key="linea-{{ $product->id }}">
    <a href="{{ $url }}" class="size-20 shrink-0 overflow-hidden rounded-lg border border-carbon-200 bg-white">
        @if($product->primaryImage)
            <img src="{{ asset($product->primaryImage->path) }}" alt="{{ $product->name }}" loading="lazy"
                 class="size-full object-contain p-1.5">
        @endif
    </a>

    <div class="min-w-0 flex-1">
        @if($product->brand)
            <p class="text-xs font-semibold uppercase tracking-wider text-carbon-400">{{ $product->brand->name }}</p>
        @endif

        <a href="{{ $url }}" class="mt-0.5 block font-display text-sm font-bold text-carbon-950 transition hover:text-brand-700">
            {{ $product->name }}
        </a>

        <p class="mt-1 text-xs text-carbon-400">SKU {{ $product->sku }}</p>

        @if($motivo = $line->quoteReason())
            <p class="mt-2 flex items-start gap-1.5 text-xs leading-relaxed text-sky-800">
                <svg class="mt-0.5 size-3.5 shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M8 5v4M8 11.5v.01" stroke-linecap="round"/><circle cx="8" cy="8" r="6.5"/>
                </svg>
                {{ $motivo }}
            </p>
        @endif
    </div>

    <div class="flex items-center gap-4">
        <div class="flex h-10 items-center rounded-full border border-carbon-300">
            <button type="button"
                    wire:click="updateQuantity({{ $product->id }}, {{ max(0, $line->quantity - 1) }})"
                    class="flex size-9 items-center justify-center rounded-full text-carbon-500 transition hover:text-carbon-950"
                    aria-label="Disminuir">
                <svg class="size-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M3.5 8h9" stroke-linecap="round"/>
                </svg>
            </button>

            <span class="w-10 text-center text-sm font-semibold tabular-nums text-carbon-950">{{ $line->quantity }}</span>

            <button type="button"
                    wire:click="updateQuantity({{ $product->id }}, {{ $line->quantity + 1 }})"
                    class="flex size-9 items-center justify-center rounded-full text-carbon-500 transition hover:text-carbon-950"
                    aria-label="Aumentar">
                <svg class="size-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M8 3.5v9M3.5 8h9" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <div class="w-28 text-right">
            <p class="font-display text-sm font-extrabold tabular-nums text-carbon-950">
                ${{ number_format($line->total(), 2) }}
            </p>
            <p class="text-xs text-carbon-400">${{ number_format($line->unitPrice(), 2) }} c/u</p>
        </div>

        <button type="button" wire:click="remove({{ $product->id }})"
                class="rounded-full p-2 text-carbon-400 transition hover:bg-carbon-100 hover:text-carbon-900"
                aria-label="Quitar {{ $product->name }} del carrito">
            <svg class="size-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path d="M3 4.5h10M6.5 4.5V3h3v1.5M5 4.5l.5 8h5l.5-8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
    </div>
</div>
