<x-layouts.app title="Solicitar renta"
    description="Dinos qué equipo necesitas, dónde y para cuándo. Confirmamos disponibilidad y tarifa el mismo día hábil."
    :hide-whatsapp="true">

    <x-ui.page-header
        eyebrow="Renta de equipos"
        title="Solicitar renta"
        lead="El formulario cambia según dónde esté tu obra: no es lo mismo mandar una regla láser a Guanajuato que entregar una compactadora en Apodaca."
        :breadcrumbs="['Renta de equipos' => route('renta.index'), 'Solicitar' => null]" />

    <section class="py-12 lg:py-16">
        <x-ui.container class="grid gap-12 lg:grid-cols-3 lg:gap-16">
            <div class="lg:col-span-2">
                <livewire:renta.solicitud />
            </div>

            <aside class="lg:sticky lg:top-32 lg:self-start">
                <div class="rounded-2xl border border-carbon-200 bg-carbon-50 p-6">
                    <h2 class="font-display text-base font-extrabold text-carbon-950">Qué pasa después</h2>
                    <ol class="mt-4 space-y-4 text-sm">
                        @foreach([
                            'Recibes tu folio de solicitud por correo, al instante.',
                            'Un agente revisa disponibilidad del equipo para tus fechas.',
                            'Te contacta con la tarifa y el detalle de la entrega.',
                            'Al confirmar, se agenda la salida del equipo.',
                        ] as $i => $paso)
                            <li class="flex gap-3">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-full border border-brand-500/50 font-display text-xs font-bold text-brand-700">
                                    {{ $i + 1 }}
                                </span>
                                <span class="leading-relaxed text-carbon-600">{{ $paso }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <div class="mt-4 rounded-2xl border border-carbon-200 p-6">
                    <h2 class="font-display text-base font-extrabold text-carbon-950">Ve juntando</h2>
                    <p class="mt-2 text-sm leading-relaxed text-carbon-600">
                        Para liberar el equipo pedimos documentación de la empresa o persona
                        que renta. Revisarla desde ahora agiliza la entrega.
                    </p>
                    <x-ui.button href="{{ route('renta.requisitos') }}" variant="outline" class="mt-5 w-full" :icon="false">
                        Ver requisitos
                    </x-ui.button>
                </div>
            </aside>
        </x-ui.container>
    </section>
</x-layouts.app>
