@props(['project', 'featured' => false])

<a href="{{ route('proyectos.show', $project) }}"
   {{ $attributes->class(['group relative flex flex-col justify-end overflow-hidden rounded-2xl bg-carbon-900', $featured ? 'min-h-[26rem]' : 'min-h-[20rem]']) }}>
    @if($project->cover_image)
        <img src="{{ asset($project->cover_image) }}" alt="{{ $project->title }}" loading="lazy"
             class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-105">
    @endif

    <div class="absolute inset-0 bg-gradient-to-t from-carbon-950 via-carbon-950/65 to-carbon-950/5"></div>

    <div class="relative p-6 sm:p-7">
        @if($project->area_m2)
            {{-- El área es el dato que mejor comunica la escala de la obra --}}
            <p class="font-display text-3xl font-extrabold leading-none text-brand-400 sm:text-4xl">
                {{ number_format($project->area_m2) }}<span class="ml-1 text-base font-bold text-brand-400/70">m²</span>
            </p>
        @endif

        <h3 @class([
            'mt-3 font-display font-extrabold leading-tight text-white',
            'text-xl sm:text-2xl' => $featured,
            'text-lg' => ! $featured,
        ])>{{ $project->title }}</h3>

        <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-white/55">
            @if($project->location)<span>{{ $project->location }}</span>@endif
            @if($project->location && $project->year)<span class="text-white/25">&middot;</span>@endif
            @if($project->year)<span>{{ $project->year }}</span>@endif
        </p>

        @if($featured && $project->services)
            <ul class="mt-4 flex flex-wrap gap-2">
                @foreach($project->services as $service)
                    <li class="rounded-full border border-white/20 px-3 py-1 text-xs font-medium text-white/75">{{ $service }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</a>
