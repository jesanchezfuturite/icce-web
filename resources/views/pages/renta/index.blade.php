<x-layouts.app title="Renta de equipos"
    description="Renta de reglas láser Somero, allanadoras, equipos vibratorios, cortadoras y maquinaria ligera para construcción. Cobertura nacional y local en Monterrey.">

    <x-ui.page-header
        eyebrow="Maquinaria ligera y equipo especializado"
        title="Renta de equipo para pisos de concreto"
        lead="Reglas láser y equipo grande con cobertura nacional; equipo menor con entrega local desde nuestro almacén en Monterrey."
        image="images/proyectos/Distribuidor-Somero-Mexico.jpg"
        :breadcrumbs="['Renta de equipos' => null]">

        <div class="mt-9 flex flex-wrap gap-3">
            <x-ui.button href="{{ route('renta.solicitar') }}" size="lg">Solicitar renta</x-ui.button>
            <x-ui.button href="{{ route('renta.requisitos') }}" variant="outline-light" size="lg" :icon="false">
                Ver requisitos
            </x-ui.button>
        </div>
    </x-ui.page-header>

    {{-- 4.1 / 4.2 Cobertura --}}
    <section class="border-b border-carbon-200 bg-carbon-50 py-14">
        <x-ui.container class="grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-carbon-200 bg-white p-7">
                <div class="flex items-center gap-3">
                    <span class="rounded-full bg-brand-500/15 px-3 py-1 text-xs font-bold uppercase tracking-wider text-brand-800">Nacional</span>
                    <span class="text-sm text-carbon-500">{{ $nationalCount }} equipos</span>
                </div>
                <h2 class="mt-4 font-display text-xl font-extrabold text-carbon-950">Reglas láser y equipo grande</h2>
                <p class="mt-2 text-sm leading-relaxed text-carbon-600">
                    Somero S-940, S-240, SRS y CopperHead. Se movilizan a cualquier estado de la República,
                    con operador capacitado y calibración previa al arranque.
                </p>
            </div>

            <div class="rounded-2xl border border-carbon-200 bg-white p-7">
                <div class="flex items-center gap-3">
                    <span class="rounded-full bg-sky-500/15 px-3 py-1 text-xs font-bold uppercase tracking-wider text-sky-800">Local</span>
                    <span class="text-sm text-carbon-500">{{ $localCount }} equipos</span>
                </div>
                <h2 class="mt-4 font-display text-xl font-extrabold text-carbon-950">Equipos menores</h2>
                <p class="mt-2 text-sm leading-relaxed text-carbon-600">
                    Vibratorios, compactadoras, cortadoras, generadores y torres de iluminación.
                    Entrega en el área metropolitana de Monterrey.
                </p>
            </div>
        </x-ui.container>
    </section>

    {{-- Catálogo informativo, sin motor de pago (REQ-06) --}}
    @forelse($equipmentByCategory as $categoryName => $equipment)
        <section class="border-b border-carbon-200 py-16 last:border-b-0 lg:py-20">
            <x-ui.container>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <h2 class="font-display text-2xl font-extrabold text-carbon-950 sm:text-3xl">{{ $categoryName }}</h2>
                    <p class="text-sm text-carbon-500">{{ $equipment->count() }} equipos disponibles</p>
                </div>

                <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($equipment as $product)
                        <x-cards.product :product="$product" />
                    @endforeach
                </div>
            </x-ui.container>
        </section>
    @empty
        <section class="py-20">
            <x-ui.container>
                <p class="text-carbon-500">Aún no hay equipos publicados en el catálogo de renta.</p>
            </x-ui.container>
        </section>
    @endforelse

    {{-- 4.3 Llamada al formulario de solicitud --}}
    <section class="bg-carbon-950 py-20 lg:py-24">
        <x-ui.container class="flex flex-col gap-8 lg:flex-row lg:items-center lg:justify-between">
            <x-ui.section-heading
                tone="light"
                eyebrow="Solicitud de renta"
                title="Dinos qué necesitas y para cuándo"
                lead="Un agente revisa disponibilidad, arma la propuesta y te contacta. El formulario adaptativo de requisitos llega en la siguiente fase del proyecto." />
            <div class="flex shrink-0 flex-wrap gap-3">
                <x-ui.button href="{{ route('renta.solicitar') }}" size="lg">Solicitar renta</x-ui.button>
                <x-ui.button href="{{ route('renta.requisitos') }}" variant="outline-light" size="lg" :icon="false">Requisitos</x-ui.button>
            </div>
        </x-ui.container>
    </section>
</x-layouts.app>
