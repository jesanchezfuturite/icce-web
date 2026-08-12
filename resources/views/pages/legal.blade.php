<x-layouts.app :title="$title" :description="$lead">

    <x-ui.page-header eyebrow="Documento legal" :title="$title" :lead="$lead" :breadcrumbs="[$title => null]" />

    <section class="py-16 lg:py-24">
        <x-ui.container size="narrow">
            <div class="rounded-2xl border border-amber-300/60 bg-amber-50 p-6 text-sm leading-relaxed text-amber-900">
                <strong class="font-semibold">Contenido pendiente.</strong>
                El texto legal definitivo lo proporciona ICCE y se carga desde el backoffice.
                Esta página existe para conservar la ruta y su redirección 301 desde el sitio anterior.
            </div>
        </x-ui.container>
    </section>
</x-layouts.app>
