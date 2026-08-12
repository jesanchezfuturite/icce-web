<x-layouts.app :title="$product->name" :description="$product->short_description">

    <x-ui.page-header
        :eyebrow="$product->brand?->name ?? 'Equipo en renta'"
        :title="$product->name"
        :breadcrumbs="[
            'Renta de equipos' => route('renta.index'),
            $product->category->name => null,
            $product->name => null,
        ]" />

    <section class="py-14 lg:py-20">
        <x-ui.container class="grid gap-12 lg:grid-cols-2 lg:gap-16">
            <div class="aspect-square overflow-hidden rounded-2xl border border-carbon-200 bg-carbon-50">
                @if($product->images->isNotEmpty())
                    <img src="{{ asset($product->images->first()->path) }}" alt="{{ $product->name }}"
                         class="size-full object-contain p-10">
                @else
                    <div class="flex size-full items-center justify-center text-carbon-300">Sin imagen</div>
                @endif
            </div>

            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 ring-1 ring-inset ring-sky-600/20">
                        <span class="size-1.5 rounded-full bg-sky-500"></span>En renta
                    </span>
                    @if($product->rental_coverage)
                        <span class="rounded-full bg-carbon-100 px-2.5 py-1 text-xs font-semibold text-carbon-700">
                            {{ $product->rental_coverage->label() }}
                        </span>
                    @endif
                    <span class="text-xs font-medium text-carbon-400">SKU {{ $product->sku }}</span>
                </div>

                @if($product->short_description)
                    <p class="mt-6 leading-relaxed text-carbon-600">{{ $product->short_description }}</p>
                @endif

                {{-- REQ-06: catálogo de renta informativo, sin motor de pago --}}
                <div class="mt-8 rounded-xl border border-carbon-200 bg-carbon-50 p-6">
                    <h2 class="font-display text-base font-bold text-carbon-950">Renta bajo cotización</h2>
                    <p class="mt-2 text-sm leading-relaxed text-carbon-600">
                        La tarifa depende del periodo, la ubicación de la obra y si requieres operador.
                        Mándanos las fechas y te confirmamos disponibilidad y precio el mismo día hábil.
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <x-ui.button href="{{ route('renta.solicitar', ['equipo' => $product->slug]) }}">
                            Solicitar este equipo
                        </x-ui.button>
                        <x-ui.button href="{{ route('renta.requisitos') }}" variant="outline" :icon="false">Ver requisitos</x-ui.button>
                    </div>
                </div>

                @if($product->specs)
                    <dl class="mt-10 divide-y divide-carbon-200 border-t border-carbon-200">
                        @foreach($product->specs as $key => $value)
                            <div class="flex justify-between gap-6 py-3 text-sm">
                                <dt class="text-carbon-500">{{ Str::headline((string) $key) }}</dt>
                                <dd class="text-right font-medium text-carbon-900">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>
        </x-ui.container>
    </section>

    @if($related->isNotEmpty())
        <section class="border-t border-carbon-200 py-16 lg:py-20">
            <x-ui.container>
                <x-ui.section-heading eyebrow="Misma familia" title="Otros equipos" />
                <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($related as $item)
                        <x-cards.product :product="$item" />
                    @endforeach
                </div>
            </x-ui.container>
        </section>
    @endif
</x-layouts.app>
