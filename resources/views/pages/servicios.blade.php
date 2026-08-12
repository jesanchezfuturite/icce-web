<x-layouts.app title="Servicios"
    description="Asesoría técnica, medición de planicidad, aplicación de productos y capacitación en obra para pisos industriales.">

    <x-ui.page-header
        eyebrow="Más allá del producto"
        title="Servicios en obra"
        lead="No solo entregamos material: acompañamos el proyecto desde la definición de tolerancias hasta la verificación del piso terminado."
        image="images/proyectos/MedicionDePlanicidad.jpg"
        :breadcrumbs="['Servicios' => null]" />

    <section class="py-20 lg:py-28">
        <x-ui.container>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach([
                    ['Medición de planicidad', 'Verificación de F-numbers (FF/FL) y tolerancias VNA con instrumentación certificada, con reporte entregable antes de instalar racks.'],
                    ['Asesoría de sistema', 'Definición de refuerzo, separación de juntas, sellador y curado según el uso real de la losa.'],
                    ['Aplicación de productos', 'Ejecución de sellado de juntas, reparación con morteros rápidos y recubrimientos.'],
                    ['Renta con operador', 'Reglas láser y allanadoras con personal capacitado para el colado.'],
                    ['Capacitación en sitio', 'Entrenamiento del equipo del cliente en operación y mantenimiento del equipo.'],
                    ['Suministro programado', 'Entregas escalonadas según el avance de obra, con fecha comprometida.'],
                ] as [$title, $copy])
                    <div class="rounded-2xl border border-carbon-200 p-7 transition hover:border-carbon-950 hover:shadow-lg hover:shadow-carbon-950/8">
                        <span class="inline-flex size-10 items-center justify-center rounded-full bg-brand-500/15">
                            <span class="size-2 rounded-full bg-brand-600"></span>
                        </span>
                        <h2 class="mt-5 font-display text-lg font-bold text-carbon-950">{{ $title }}</h2>
                        <p class="mt-2 text-sm leading-relaxed text-carbon-600">{{ $copy }}</p>
                    </div>
                @endforeach
            </div>
        </x-ui.container>
    </section>
</x-layouts.app>
