<x-layouts.app title="Quiénes somos"
    description="Desde 1992 ICCE abastece al sector de la construcción con herramienta, materiales y renta de equipo para pisos industriales de concreto.">

    <x-ui.page-header
        eyebrow="Desde 1992"
        title="Resolvemos pisos de concreto, no vendemos catálogo"
        lead="Tres décadas abasteciendo obra en México: herramienta de acabado, químicos de especialidad y maquinaria en renta, con la asesoría técnica que hace que el piso salga bien a la primera."
        image="images/proyectos/MedicionDePlanicidad.jpg"
        :breadcrumbs="['Empresa' => null]" />

    {{-- 2.1 Quiénes somos y cobertura --}}
    <section class="py-20 lg:py-28">
        <x-ui.container class="grid gap-14 lg:grid-cols-2 lg:gap-20">
            <div>
                <x-ui.section-heading eyebrow="Quiénes somos" title="Un proveedor que ha estado en la obra" />
                <div class="mt-6 space-y-5 text-base leading-relaxed text-carbon-600">
                    <p>
                        ICCE Rentas y Servicios nació en 1992 dando servicio de renta de equipo al sector de la
                        construcción. Con los años el negocio creció hacia donde el cliente tenía el problema real:
                        la herramienta correcta, el material adecuado y el criterio para aplicarlo.
                    </p>
                    <p>
                        Hoy somos distribuidores autorizados de Somero, Kraft Tool, Husqvarna, CTS Rapid Set y
                        W. R. Meadows, entre otras marcas. Eso significa refacción disponible, respaldo técnico del
                        fabricante y capacitación para el equipo que va a operar en campo.
                    </p>
                    <p>
                        Atendemos naves industriales, centros de distribución y plantas de manufactura donde el piso
                        no es acabado: es infraestructura de operación.
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach([
                    ['Cobertura nacional', 'Reglas láser y equipo grande viajan a toda la República.'],
                    ['Almacén en Monterrey', 'Equipo menor y herramienta con entrega local inmediata.'],
                    ['Asesoría técnica', 'Recomendación de producto según tolerancia y tipo de losa.'],
                    ['Capacitación en obra', 'Puesta en marcha y entrenamiento del operador en sitio.'],
                ] as [$title, $copy])
                    <div class="rounded-2xl border border-carbon-200 bg-carbon-50 p-6">
                        <h3 class="font-display text-base font-bold text-carbon-950">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-carbon-600">{{ $copy }}</p>
                    </div>
                @endforeach
            </div>
        </x-ui.container>
    </section>

    {{-- 2.2 Ventajas competitivas --}}
    <section class="bg-carbon-950 py-20 lg:py-28">
        <x-ui.container>
            <x-ui.section-heading
                tone="light"
                eyebrow="Filosofía de servicio"
                title="Lo que nos piden y lo que entregamos" />

            <div class="mt-14 grid gap-px overflow-hidden rounded-2xl border border-white/10 bg-white/10 sm:grid-cols-2 lg:grid-cols-3">
                @foreach([
                    ['01', 'Existencia real', 'El catálogo dice si algo está en almacén o va bajo pedido. Sin sorpresas al confirmar.'],
                    ['02', 'Fecha comprometida', 'Cada pedido lleva fecha estimada de entrega y estatus consultable en tu portal.'],
                    ['03', 'Precio de proyecto', 'Los pedidos de volumen pasan a cotización con descuento por escala, no a lista.'],
                    ['04', 'Refacción de marca', 'Somos distribuidor: la refacción viene del fabricante, no de un genérico.'],
                    ['05', 'Renta con respaldo', 'El equipo sale calibrado y con operador capacitado si el proyecto lo requiere.'],
                    ['06', 'Criterio técnico', 'Te decimos cuándo un producto no es el indicado para tu losa.'],
                ] as [$num, $title, $copy])
                    <div class="bg-carbon-950 p-8">
                        <span class="font-display text-xs font-bold tracking-[0.2em] text-brand-500">{{ $num }}</span>
                        <h3 class="mt-4 font-display text-lg font-bold text-white">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-white/55">{{ $copy }}</p>
                    </div>
                @endforeach
            </div>
        </x-ui.container>
    </section>

    {{-- Marcas --}}
    <section class="py-20 lg:py-24">
        <x-ui.container>
            <x-ui.section-heading align="center" class="mx-auto"
                eyebrow="Distribución autorizada"
                title="Las marcas con las que trabajamos" />

            <div class="mt-12 grid grid-cols-2 items-center gap-8 sm:grid-cols-3 lg:grid-cols-6">
                @foreach($brands as $brand)
                    <a href="{{ route('marcas.show', $brand) }}" class="flex items-center justify-center" title="{{ $brand->name }}">
                        <img src="{{ asset($brand->logo_path) }}" alt="{{ $brand->name }}" loading="lazy"
                             class="h-11 w-auto opacity-50 grayscale transition duration-300 hover:opacity-100 hover:grayscale-0">
                    </a>
                @endforeach
            </div>
        </x-ui.container>
    </section>

    {{-- Proyectos --}}
    @if($projects->isNotEmpty())
        <section class="border-t border-carbon-200 py-20 lg:py-28">
            <x-ui.container>
                <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                    <x-ui.section-heading eyebrow="Casos de éxito" title="Obras recientes" />
                    <x-ui.button href="{{ route('proyectos.index') }}" variant="outline" class="shrink-0">Ver todos</x-ui.button>
                </div>

                <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($projects as $project)
                        <x-cards.project :project="$project" />
                    @endforeach
                </div>
            </x-ui.container>
        </section>
    @endif
</x-layouts.app>
