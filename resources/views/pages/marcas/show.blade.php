<x-layouts.app :title="$brand->name" :description="$brand->description">

    <x-ui.page-header
        eyebrow="Distribuidor autorizado"
        :title="$brand->name"
        :lead="$brand->description"
        :breadcrumbs="['Marcas' => route('marcas.index'), $brand->name => null]">

        @if($brand->website)
            <a href="{{ $brand->website }}" target="_blank" rel="noopener noreferrer"
               class="mt-7 inline-flex items-center gap-2 text-sm font-semibold text-brand-400 transition hover:text-brand-300">
                Sitio oficial del fabricante
                <svg class="size-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path d="M6 3h7v7M13 3 4 12" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
        @endif
    </x-ui.page-header>

    <section class="py-14 lg:py-20">
        <x-ui.container>
            <p class="text-sm text-carbon-500">{{ $products->total() }} productos de {{ $brand->name }}</p>

            <div class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @forelse($products as $product)
                    <x-cards.product :product="$product" />
                @empty
                    <p class="col-span-full text-carbon-500">Aún no hay productos publicados de esta marca.</p>
                @endforelse
            </div>

            @if($products->hasPages())
                <div class="mt-14">{{ $products->links() }}</div>
            @endif
        </x-ui.container>
    </section>
</x-layouts.app>
