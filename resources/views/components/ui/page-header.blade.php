@props([
    'eyebrow' => null,
    'title',
    'lead' => null,
    'image' => null,
    'breadcrumbs' => [],
])

<section @class([
    'relative isolate overflow-hidden bg-carbon-950',
    'py-16 lg:py-24' => ! $image,
    'py-20 lg:py-32' => $image,
])>
    @if($image)
        <img src="{{ asset($image) }}" alt="" class="absolute inset-0 size-full object-cover opacity-40">
        <div class="absolute inset-0 bg-gradient-to-t from-carbon-950 via-carbon-950/75 to-carbon-950/40"></div>
    @endif

    <x-ui.container class="relative">
        @if($breadcrumbs)
            @php
                $migas = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => url('/')]];
                foreach (array_keys($breadcrumbs) as $i => $etiqueta) {
                    $destino = $breadcrumbs[$etiqueta];
                    $migas[] = array_filter([
                        '@type' => 'ListItem',
                        'position' => $i + 2,
                        'name' => $etiqueta,
                        'item' => $destino ?: url()->current(),
                    ]);
                }
            @endphp

            <x-seo.json-ld :data="[
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => $migas,
            ]" />

            <nav aria-label="Ruta de navegación" class="mb-7">
                <ol class="flex flex-wrap items-center gap-2 text-sm text-white/45">
                    <li><a href="{{ route('home') }}" class="transition hover:text-white">Inicio</a></li>
                    @foreach($breadcrumbs as $label => $url)
                        <li aria-hidden="true" class="text-white/25">/</li>
                        <li>
                            @if($url && ! $loop->last)
                                <a href="{{ $url }}" class="transition hover:text-white">{{ $label }}</a>
                            @else
                                <span class="text-white/80">{{ $label }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        @if($eyebrow)
            <x-ui.eyebrow tone="light">{{ $eyebrow }}</x-ui.eyebrow>
        @endif

        <h1 class="mt-5 max-w-4xl font-display text-4xl font-extrabold leading-[1.05] text-white sm:text-5xl lg:text-[3.25rem]">
            {{ $title }}
        </h1>

        @if($lead)
            <p class="mt-5 max-w-2xl text-base leading-relaxed text-white/60 sm:text-lg">{{ $lead }}</p>
        @endif

        {{ $slot }}
    </x-ui.container>

    <div class="scale-rule absolute inset-x-0 bottom-0 h-1 opacity-70"></div>
</section>
