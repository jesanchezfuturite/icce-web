<x-layouts.app title="Marcas"
    description="Somero, Kraft Tool, Husqvarna, CTS Rapid Set, W. R. Meadows, Sika, Mapei y más marcas que ICCE distribuye en México.">

    <x-ui.page-header
        eyebrow="Distribución autorizada"
        title="Marcas que distribuimos"
        lead="Ser distribuidor autorizado significa refacción original, respaldo técnico del fabricante y capacitación para tu equipo."
        :breadcrumbs="['Marcas' => null]" />

    <section class="py-16 lg:py-24">
        <x-ui.container>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($brands as $brand)
                    <a href="{{ route('marcas.show', $brand) }}"
                       class="group flex flex-col rounded-2xl border border-carbon-200 p-7 transition hover:-translate-y-0.5 hover:border-carbon-950 hover:shadow-xl hover:shadow-carbon-950/8">
                        <div class="flex h-14 items-center">
                            @if($brand->logo_path)
                                <img src="{{ asset($brand->logo_path) }}" alt="{{ $brand->name }}" loading="lazy"
                                     class="max-h-12 w-auto opacity-70 grayscale transition group-hover:opacity-100 group-hover:grayscale-0">
                            @else
                                <span class="font-display text-lg font-extrabold text-carbon-950">{{ $brand->name }}</span>
                            @endif
                        </div>

                        <h2 class="mt-5 font-display text-base font-bold text-carbon-950">{{ $brand->name }}</h2>
                        <p class="mt-2 flex-1 text-sm leading-relaxed text-carbon-600">{{ $brand->description }}</p>
                        <p class="mt-5 text-xs font-semibold uppercase tracking-wider text-carbon-400">
                            {{ $brand->products_count }} productos
                        </p>
                    </a>
                @endforeach
            </div>
        </x-ui.container>
    </section>
</x-layouts.app>
