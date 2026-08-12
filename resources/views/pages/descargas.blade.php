<x-layouts.app title="Centro de descargas"
    description="Fichas técnicas y hojas de seguridad de los productos que distribuye ICCE: Somero, Husqvarna, Mapei, Sika, W. R. Meadows y más.">

    <x-ui.page-header
        eyebrow="Recursos"
        title="Centro de descargas"
        lead="Fichas técnicas y hojas de seguridad de fábrica, listas para tu expediente de obra o tu memoria de cálculo."
        :breadcrumbs="['Centro de descargas' => null]" />

    <section class="py-12 lg:py-16">
        <x-ui.container>
            <livewire:descargas.centro />

            <p class="mt-12 rounded-xl border border-carbon-200 bg-carbon-50 p-5 text-sm leading-relaxed text-carbon-600">
                Los documentos son los que publica cada fabricante. Si necesitas una ficha que no
                aparece aquí, o la versión más reciente de una norma, escríbenos y la conseguimos.
            </p>
        </x-ui.container>
    </section>
</x-layouts.app>
