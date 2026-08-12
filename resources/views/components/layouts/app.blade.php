@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'bodyClass' => '',
    // El botón flotante se oculta donde competiría con la acción principal
    // de la página, como el "Confirmar y pagar" del checkout.
    'hideWhatsapp' => false,
])

<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ? $title.' | ICCE Rentas y Servicios' : 'ICCE Rentas y Servicios | Pisos industriales y pisos superplanos' }}</title>
    <meta name="description" content="{{ $description ?? 'Venta y renta de herramienta, maquinaria y materiales para pisos industriales de concreto. Distribuidor Somero, Kraft Tool y Husqvarna en México desde 1992.' }}">

    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title ?? 'ICCE Rentas y Servicios' }}">
    <meta property="og:description" content="{{ $description ?? 'Pisos industriales y pisos superplanos. Venta y renta de equipo especializado.' }}">
    <meta property="og:image" content="{{ asset($image ?? 'images/proyectos/MedicionDePlanicidad.jpg') }}">
    <meta property="og:locale" content="es_MX">

    <link rel="canonical" href="{{ url()->current() }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    {{-- Identidad del negocio para el buscador --}}
    <x-seo.json-ld :data="[
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => url('/#organizacion'),
                'name' => 'ICCE Rentas y Servicios',
                'url' => url('/'),
                'logo' => asset('images/marca/logo-icce.png'),
                'foundingDate' => '1992',
                'description' => 'Venta y renta de herramienta, maquinaria y materiales para pisos industriales y pisos superplanos de concreto.',
                'areaServed' => ['@type' => 'Country', 'name' => 'México'],
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => 'Monterrey',
                    'addressRegion' => 'Nuevo León',
                    'addressCountry' => 'MX',
                ],
                'contactPoint' => [
                    '@type' => 'ContactPoint',
                    'contactType' => 'sales',
                    'email' => config('icce.sales_email'),
                    'availableLanguage' => ['es'],
                ],
            ],
            [
                '@type' => 'WebSite',
                '@id' => url('/#sitio'),
                'url' => url('/'),
                'name' => 'ICCE Rentas y Servicios',
                'inLanguage' => 'es-MX',
                'publisher' => ['@id' => url('/#organizacion')],
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => [
                        '@type' => 'EntryPoint',
                        'urlTemplate' => url('/catalogo').'?q={search_term_string}',
                    ],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ],
    ]" />
</head>
<body class="min-h-screen bg-white text-carbon-800 font-sans {{ $bodyClass }}">
    <a href="#contenido"
       class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[100] focus:rounded-full focus:bg-carbon-950 focus:px-5 focus:py-3 focus:text-sm focus:font-semibold focus:text-white">
        Saltar al contenido
    </a>

    <x-site.header />

    <main id="contenido">
        {{ $slot }}
    </main>

    <x-site.footer />

    @unless($hideWhatsapp)
        <x-site.whatsapp-button />
    @endunless

    {{-- Livewire trae Alpine incluido; se carga explícitamente para no depender
         de la auto-inyección y que los menús funcionen en páginas sin componentes. --}}
    @livewireScripts
</body>
</html>
