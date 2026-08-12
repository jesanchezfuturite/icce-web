<x-layouts.app title="Proyectos"
    description="Galería de obras atendidas: naves industriales, centros de distribución y plantas de manufactura con piso superplano.">

    <x-ui.page-header
        eyebrow="Casos de éxito"
        title="Obras donde ya estuvimos"
        lead="Naves industriales, centros de distribución y plantas de manufactura. Metros cuadrados, tolerancias y equipo empleado en cada una."
        image="images/proyectos/VNA.jpg"
        :breadcrumbs="['Proyectos' => null]" />

    <section class="py-16 lg:py-24">
        <x-ui.container>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($projects as $project)
                    <x-cards.project :project="$project" :featured="$loop->first" :class="$loop->first ? 'sm:col-span-2' : ''" />
                @endforeach
            </div>

            @if($projects->hasPages())
                <div class="mt-14">{{ $projects->links() }}</div>
            @endif
        </x-ui.container>
    </section>
</x-layouts.app>
