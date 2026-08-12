@props(['product'])

@php
    $url = $product->is_rental ? route('renta.equipo', $product) : route('producto', $product);
    $image = $product->primaryImage?->path;
@endphp

<a href="{{ $url }}"
   class="group flex flex-col overflow-hidden rounded-xl border border-carbon-200 bg-white transition duration-200 hover:-translate-y-0.5 hover:border-carbon-300 hover:shadow-xl hover:shadow-carbon-950/8">
    {{-- Fondo blanco: las fotos heredadas del sitio anterior vienen recortadas
         sobre blanco y sobre un gris se verían como un recuadro pegado. --}}
    <div class="relative aspect-4/3 overflow-hidden bg-white">
        @if($image)
            <img src="{{ asset($image) }}" alt="{{ $product->name }}" loading="lazy"
                 class="size-full object-contain p-5 transition duration-300 group-hover:scale-105">
        @else
            <div class="flex size-full items-center justify-center">
                <svg class="size-10 text-carbon-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z" stroke-linejoin="round"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9" stroke-linejoin="round"/>
                </svg>
            </div>
        @endif

        <div class="absolute left-3 top-3">
            @if($product->is_rental)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 ring-1 ring-inset ring-sky-600/20">
                    <span class="size-1.5 rounded-full bg-sky-500"></span>En renta
                </span>
            @else
                <x-ui.stock-badge :product="$product" />
            @endif
        </div>
    </div>

    <div class="flex flex-1 flex-col gap-2 border-t border-carbon-200 p-4">
        @if($product->brand)
            <p class="text-xs font-semibold uppercase tracking-wider text-carbon-400">{{ $product->brand->name }}</p>
        @endif

        <h3 class="font-display text-sm font-bold leading-snug text-carbon-950 transition group-hover:text-brand-700">
            {{ $product->name }}
        </h3>

        <div class="mt-auto flex items-end justify-between pt-2">
            @if($product->is_rental)
                <span class="text-sm font-semibold text-carbon-600">Cotización a medida</span>
            @else
                <div>
                    <span class="font-display text-lg font-extrabold text-carbon-950">
                        ${{ number_format((float) $product->price, 2) }}
                    </span>
                    <span class="text-xs text-carbon-500">MXN / {{ $product->unit }}</span>
                </div>
            @endif

            <span class="text-xs font-semibold text-brand-700 opacity-0 transition group-hover:opacity-100">Ver ficha &rarr;</span>
        </div>
    </div>
</a>
