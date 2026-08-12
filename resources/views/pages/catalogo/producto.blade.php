@php
    use App\Enums\PurchaseMode;

    $breadcrumbs = ['Catálogo' => route('catalogo.index')];
    if ($product->category?->parent) {
        $breadcrumbs[$product->category->parent->name] = route('catalogo.categoria', $product->category->parent);
    }
    if ($product->category) {
        $breadcrumbs[$product->category->name] = route('catalogo.categoria', $product->category);
    }
    $breadcrumbs[$product->name] = null;

    $mode = $product->purchaseModeFor(1);
    $images = $product->images;
@endphp

<x-layouts.app :title="$product->name"
    :description="$product->meta_description ?? $product->short_description"
    :image="$images->first()?->path">

    {{-- Ficha de producto para el buscador: precio, existencia y marca --}}
    <x-seo.json-ld :data="array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product->name,
        'sku' => $product->sku,
        'description' => $product->short_description ?: $product->name,
        'image' => $images->isNotEmpty() ? asset($images->first()->path) : null,
        'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
        'category' => $product->category?->name,
        // Sólo se declara oferta si hay un precio real que ofrecer: publicar
        // $0.00 haría que el buscador muestre un precio falso.
        'offers' => (float) $product->price > 0 ? [
            '@type' => 'Offer',
            'url' => url()->current(),
            'priceCurrency' => 'MXN',
            'price' => (string) $product->price,
            'availability' => match (true) {
                $product->is_on_demand => 'https://schema.org/PreOrder',
                $product->stock_qty > 0 => 'https://schema.org/InStock',
                default => 'https://schema.org/OutOfStock',
            },
            'seller' => ['@id' => url('/#organizacion')],
        ] : null,
    ])" />

    <x-ui.page-header :eyebrow="$product->brand?->name" :title="$product->name" :breadcrumbs="$breadcrumbs" />

    <section class="py-14 lg:py-20">
        <x-ui.container class="grid gap-12 lg:grid-cols-2 lg:gap-16">
            {{-- 3.3 Galería --}}
            <div x-data="{ activa: 0 }">
                <div class="aspect-square overflow-hidden rounded-2xl border border-carbon-200 bg-white">
                    @forelse($images as $index => $image)
                        {{-- Sólo las secundarias llevan x-cloak: la primera debe
                             verse aunque Alpine todavía no haya arrancado. --}}
                        <img x-show="activa === {{ $index }}" @if($index > 0) x-cloak @endif
                             src="{{ asset($image->path) }}" alt="{{ $image->alt ?? $product->name }}"
                             class="size-full object-contain p-10"
                             @if($index === 0) fetchpriority="high" @else loading="lazy" @endif>
                    @empty
                        <div class="flex size-full items-center justify-center">
                            <svg class="size-16 text-carbon-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z" stroke-linejoin="round"/>
                                <path d="m4 7.5 8 4.5 8-4.5M12 12v9" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    @endforelse
                </div>

                @if($images->count() > 1)
                    <div class="mt-4 flex flex-wrap gap-3">
                        @foreach($images as $index => $image)
                            <button type="button" @click="activa = {{ $index }}"
                                    class="size-20 overflow-hidden rounded-lg border-2 bg-white transition"
                                    :class="activa === {{ $index }} ? 'border-brand-500' : 'border-carbon-200 hover:border-carbon-400'"
                                    aria-label="Ver imagen {{ $index + 1 }}">
                                <img src="{{ asset($image->path) }}" alt="" loading="lazy" class="size-full object-contain p-2">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Ficha --}}
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.stock-badge :product="$product" />
                    <span class="text-xs font-medium text-carbon-400">SKU {{ $product->sku }}</span>
                    @if($product->brand)
                        <a href="{{ route('marcas.show', $product->brand) }}"
                           class="text-xs font-semibold text-brand-700 underline transition hover:text-brand-800">
                            Ver todo de {{ $product->brand->name }}
                        </a>
                    @endif
                </div>

                @if($product->short_description)
                    <p class="mt-6 leading-relaxed text-carbon-600">{{ $product->short_description }}</p>
                @endif

                <div class="mt-8 border-y border-carbon-200 py-6">
                    <p class="font-display text-3xl font-extrabold text-carbon-950">
                        ${{ number_format((float) $product->price, 2) }}
                        <span class="text-sm font-semibold text-carbon-500">MXN / {{ $product->unit }}</span>
                    </p>
                    <p class="mt-1 text-xs text-carbon-400">Precio antes de IVA</p>
                </div>

                {{-- Motor de decisión en vivo: el modo cambia con la cantidad --}}
                <div class="mt-8">
                    <livewire:carrito.agregar :product="$product" />
                </div>

                {{-- 3.3 Descargas --}}
                @if($product->tech_sheet_pdf || $product->safety_sheet_pdf)
                    <div class="mt-10">
                        <h2 class="font-display text-sm font-bold uppercase tracking-[0.16em] text-carbon-950">Documentación</h2>
                        <div class="mt-4 space-y-2">
                            @foreach([
                                ['Ficha técnica', $product->tech_sheet_pdf],
                                ['Hoja de seguridad', $product->safety_sheet_pdf],
                            ] as [$label, $path])
                                @if($path)
                                    <a href="{{ asset($path) }}" target="_blank" rel="noopener"
                                       class="group flex items-center gap-4 rounded-xl border border-carbon-200 p-4 transition hover:border-carbon-950 hover:bg-carbon-50">
                                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-700">
                                            <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                                <path d="M11.5 2H5.5A1.5 1.5 0 0 0 4 3.5v13A1.5 1.5 0 0 0 5.5 18h9a1.5 1.5 0 0 0 1.5-1.5V6.5L11.5 2Z" stroke-linejoin="round"/>
                                                <path d="M11 2v5h5" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                        <span class="flex-1">
                                            <span class="block text-sm font-semibold text-carbon-950">{{ $label }}</span>
                                            <span class="block text-xs text-carbon-500">PDF del fabricante</span>
                                        </span>
                                        <svg class="size-4 text-carbon-400 transition group-hover:translate-y-0.5 group-hover:text-carbon-950"
                                             viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                            <path d="M8 3v9m0 0 4-4m-4 4-4-4M3 14h10" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Especificaciones --}}
                @php $specs = collect($product->specs ?? [])->except('imagen_origen'); @endphp
                @if($specs->isNotEmpty())
                    <div class="mt-10">
                        <h2 class="font-display text-sm font-bold uppercase tracking-[0.16em] text-carbon-950">Especificaciones</h2>
                        <dl class="mt-4 divide-y divide-carbon-200 border-y border-carbon-200">
                            @foreach($specs as $key => $value)
                                <div class="flex justify-between gap-6 py-3 text-sm">
                                    <dt class="text-carbon-500">{{ Str::headline((string) $key) }}</dt>
                                    <dd class="text-right font-medium text-carbon-900">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endif

                @if($product->description && $product->description !== $product->short_description)
                    <div class="mt-10">
                        <h2 class="font-display text-sm font-bold uppercase tracking-[0.16em] text-carbon-950">Descripción</h2>
                        <p class="mt-4 leading-relaxed text-carbon-600">{{ $product->description }}</p>
                    </div>
                @endif
            </div>
        </x-ui.container>
    </section>

    @if($related->isNotEmpty())
        <section class="border-t border-carbon-200 py-16 lg:py-20">
            <x-ui.container>
                <x-ui.section-heading eyebrow="También en esta categoría" title="Productos relacionados" />
                <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($related as $item)
                        <x-cards.product :product="$item" />
                    @endforeach
                </div>
            </x-ui.container>
        </section>
    @endif
</x-layouts.app>
