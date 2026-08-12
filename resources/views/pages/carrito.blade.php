<x-layouts.app title="Carrito y cotización" description="Revisa tu pedido antes de pagar o solicitar cotización." :hide-whatsapp="true">

    <x-ui.page-header
        eyebrow="Tu pedido"
        title="Carrito y cotizador"
        lead="Puedes mezclar material con existencia y pedidos de volumen: cada parte sigue su propio camino."
        :breadcrumbs="['Carrito' => null]" />

    <section class="py-12 lg:py-16">
        <x-ui.container>
            @if(session('aviso'))
                <div class="mb-8 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                    {{ session('aviso') }}
                </div>
            @endif

            <livewire:carrito.detalle />
        </x-ui.container>
    </section>
</x-layouts.app>
