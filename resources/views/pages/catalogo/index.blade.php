<x-layouts.app title="Catálogo"
    description="Herramienta para concreto, materiales y químicos, transferencia de carga, desbaste y abrillantado, y corte de concreto. Búsqueda por nombre, SKU o marca.">

    <x-ui.page-header
        eyebrow="Catálogo"
        title="Todo para el piso industrial"
        lead="Herramienta de acabado, químicos de especialidad y accesorios de junta. Con existencia en almacén o bajo pedido programado."
        :breadcrumbs="['Catálogo' => null]" />

    {{-- Accesos por familia, antes del explorador --}}
    <section class="border-b border-carbon-200 bg-carbon-50">
        <x-ui.container class="flex flex-wrap gap-2 py-5">
            @foreach($categories as $category)
                <a href="{{ route('catalogo.categoria', $category) }}"
                   class="rounded-full border border-carbon-300 bg-white px-4 py-1.5 text-xs font-semibold text-carbon-700 transition hover:border-carbon-950">
                    {{ $category->name }}
                    <span class="ml-1 text-carbon-400">{{ $category->totalProducts() }}</span>
                </a>
            @endforeach
        </x-ui.container>
    </section>

    <section class="py-12 lg:py-16">
        <x-ui.container>
            <livewire:catalogo.explorador />
        </x-ui.container>
    </section>
</x-layouts.app>
