<x-layouts.app :title="$project->title" :description="$project->summary" :image="$project->cover_image">

    <x-ui.page-header
        eyebrow="Caso de éxito"
        :title="$project->title"
        :lead="$project->summary"
        :image="$project->cover_image"
        :breadcrumbs="['Proyectos' => route('proyectos.index'), $project->title => null]" />

    <section class="py-16 lg:py-24">
        <x-ui.container class="grid gap-14 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-carbon-200 bg-carbon-200 sm:grid-cols-4">
                    @foreach([
                        'Cliente' => $project->client,
                        'Ubicación' => $project->location,
                        'Año' => $project->year,
                        'Superficie' => $project->area_m2 ? number_format($project->area_m2).' m²' : null,
                    ] as $label => $value)
                        @if($value)
                            <div class="bg-white p-5">
                                <dt class="text-xs uppercase tracking-wider text-carbon-400">{{ $label }}</dt>
                                <dd class="mt-1.5 font-display text-sm font-bold text-carbon-950">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>

                @if($project->body)
                    <div class="mt-10 space-y-5 leading-relaxed text-carbon-700">
                        @foreach(preg_split('/\n\s*\n/', $project->body) as $paragraph)
                            @if(trim($paragraph) !== '')<p>{{ trim($paragraph) }}</p>@endif
                        @endforeach
                    </div>
                @endif
            </div>

            <aside class="lg:sticky lg:top-32 lg:self-start">
                @if($project->services)
                    <h2 class="font-display text-sm font-bold uppercase tracking-[0.16em] text-carbon-950">Servicios y equipo</h2>
                    <ul class="mt-5 space-y-2.5">
                        @foreach($project->services as $service)
                            <li class="flex gap-3 text-sm text-carbon-600">
                                <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-brand-500"></span>
                                {{ $service }}
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="mt-8 rounded-2xl border border-carbon-200 bg-carbon-50 p-6">
                    <p class="text-sm leading-relaxed text-carbon-600">
                        ¿Tienes un proyecto similar? Te ayudamos a definir tolerancias, equipo y programa de colado.
                    </p>
                    <x-ui.button href="{{ route('contacto') }}" class="mt-5 w-full">Platícanos tu obra</x-ui.button>
                </div>
            </aside>
        </x-ui.container>
    </section>

    @if($related->isNotEmpty())
        <section class="border-t border-carbon-200 py-16 lg:py-20">
            <x-ui.container>
                <x-ui.section-heading eyebrow="Más obras" title="Otros proyectos" />
                <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($related as $item)
                        <x-cards.project :project="$item" />
                    @endforeach
                </div>
            </x-ui.container>
        </section>
    @endif
</x-layouts.app>
