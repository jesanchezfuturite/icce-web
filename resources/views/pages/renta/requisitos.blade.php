<x-layouts.app title="Requisitos de renta"
    description="Documentación y condiciones para rentar maquinaria ligera y equipo especializado en ICCE Rentas y Servicios.">

    <x-ui.page-header
        eyebrow="Antes de rentar"
        title="Requisitos de renta"
        lead="Lo que necesitamos para liberar el equipo, tanto para cliente nuevo como recurrente."
        :breadcrumbs="['Renta de equipos' => route('renta.index'), 'Requisitos' => null]" />

    <section class="py-20 lg:py-24">
        <x-ui.container class="grid gap-12 lg:grid-cols-3">
            <div class="lg:col-span-2 lg:pr-10">
                @foreach([
                    ['Persona moral', [
                        'Acta constitutiva y poder del representante legal',
                        'Constancia de situación fiscal vigente',
                        'Identificación oficial del representante legal',
                        'Comprobante de domicilio fiscal (no mayor a 3 meses)',
                        'Datos de la obra: dirección, contacto en sitio y fechas',
                    ]],
                    ['Persona física', [
                        'Identificación oficial vigente',
                        'Constancia de situación fiscal',
                        'Comprobante de domicilio (no mayor a 3 meses)',
                        'Datos de la obra y responsable en sitio',
                    ]],
                    ['Condiciones generales', [
                        'Depósito en garantía según el equipo solicitado',
                        'El periodo de renta corre desde la salida del almacén',
                        'El combustible y los consumibles corren por cuenta del cliente',
                        'El equipo se entrega y se recibe con revisión firmada por ambas partes',
                        'Daños por mal uso se cotizan aparte del costo de renta',
                    ]],
                ] as $index => [$title, $items])
                    <div @class(['mt-12' => ! $loop->first])>
                        <h2 class="font-display text-xl font-extrabold text-carbon-950 sm:text-2xl">{{ $title }}</h2>
                        <ul class="mt-5 space-y-3">
                            @foreach($items as $item)
                                <li class="flex gap-3 text-sm leading-relaxed text-carbon-600">
                                    <svg class="mt-0.5 size-4 shrink-0 text-brand-600" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="m3.5 8.5 3 3 6-7" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    {{ $item }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach

                <p class="mt-12 rounded-xl border border-amber-300/60 bg-amber-50 p-5 text-sm leading-relaxed text-amber-900">
                    Este listado es la base del formulario adaptativo de solicitud (REQ-07). El contenido definitivo
                    lo confirma ICCE antes de salir a producción.
                </p>
            </div>

            <aside class="lg:sticky lg:top-32 lg:self-start">
                <div class="rounded-2xl border border-carbon-200 bg-carbon-50 p-7">
                    <h2 class="font-display text-lg font-extrabold text-carbon-950">¿Listo para solicitar?</h2>
                    <p class="mt-2 text-sm leading-relaxed text-carbon-600">
                        Mándanos el equipo, la ubicación de la obra y las fechas. Te confirmamos disponibilidad el mismo día.
                    </p>
                    <x-ui.button href="{{ route('renta.solicitar') }}" class="mt-6 w-full">Solicitar renta</x-ui.button>
                    <x-ui.button href="{{ route('renta.index') }}" variant="outline" class="mt-3 w-full" :icon="false">
                        Ver equipos
                    </x-ui.button>
                </div>
            </aside>
        </x-ui.container>
    </section>
</x-layouts.app>
