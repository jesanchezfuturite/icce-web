@php
    $navCategories = collect($navCategories ?? []);
@endphp

<footer class="bg-carbon-950 text-white">
    {{-- Franja de cierre: última llamada a cotizar --}}
    <div class="border-b border-white/10">
        <x-ui.container class="flex flex-col gap-6 py-12 lg:flex-row lg:items-center lg:justify-between lg:py-16">
            <div class="max-w-xl">
                <h2 class="font-display text-2xl font-extrabold leading-tight sm:text-3xl">
                    ¿Tu obra necesita piso superplano?
                </h2>
                <p class="mt-3 text-white/60">
                    Cotizamos herramienta, material y renta de equipo en un solo pedido, con fecha de entrega comprometida.
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <x-ui.button href="/contacto" size="lg">Solicitar cotización</x-ui.button>
                <x-ui.button href="/renta" variant="outline-light" size="lg" :icon="false">Ver equipos en renta</x-ui.button>
            </div>
        </x-ui.container>
    </div>

    <x-ui.container class="grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-4 lg:py-16">
        <div>
            <img src="{{ asset('images/marca/logo-icce.png') }}" alt="ICCE Rentas y Servicios" class="h-8 w-auto">
            <p class="mt-5 text-sm leading-relaxed text-white/55">
                Desde 1992 abastecemos al sector de la construcción con herramienta, materiales
                y renta de equipo para pisos industriales y pisos superplanos de concreto.
            </p>
            <div class="mt-6 flex gap-3">
                <a href="https://www.facebook.com/ICCERYS" target="_blank" rel="noopener noreferrer"
                   class="rounded-full border border-white/15 p-2.5 text-white/60 transition hover:border-brand-500 hover:text-brand-400" aria-label="Facebook">
                    <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14 9h3V6h-3c-2.2 0-4 1.8-4 4v2H8v3h2v7h3v-7h2.5l.5-3H13v-2c0-.6.4-1 1-1z"/></svg>
                </a>
                <a href="https://g.page/ICCERYS?share" target="_blank" rel="noopener noreferrer"
                   class="rounded-full border border-white/15 p-2.5 text-white/60 transition hover:border-brand-500 hover:text-brand-400" aria-label="Ubicación en Google Maps">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z" stroke-linejoin="round"/><circle cx="12" cy="10" r="2.5"/>
                    </svg>
                </a>
            </div>
        </div>

        <div>
            <h3 class="font-display text-sm font-bold uppercase tracking-[0.16em] text-white">Catálogo</h3>
            <ul class="mt-5 space-y-2.5 text-sm">
                @foreach($navCategories->take(6) as $root)
                    <li><a href="/catalogo/{{ $root['slug'] }}" class="text-white/55 transition hover:text-brand-400">{{ $root['name'] }}</a></li>
                @endforeach
            </ul>
        </div>

        <div>
            <h3 class="font-display text-sm font-bold uppercase tracking-[0.16em] text-white">Empresa</h3>
            <ul class="mt-5 space-y-2.5 text-sm">
                @foreach([
                    'Quiénes somos' => '/empresa',
                    'Proyectos atendidos' => '/proyectos',
                    'Blog técnico' => '/blog',
                    'Centro de descargas' => '/descargas',
                    'Marcas que distribuimos' => '/marcas',
                    'Requisitos de renta' => '/renta/requisitos',
                    'Aviso de privacidad' => '/aviso-de-privacidad',
                ] as $label => $url)
                    <li><a href="{{ $url }}" class="text-white/55 transition hover:text-brand-400">{{ $label }}</a></li>
                @endforeach
            </ul>
        </div>

        <div>
            <h3 class="font-display text-sm font-bold uppercase tracking-[0.16em] text-white">Contacto</h3>
            <ul class="mt-5 space-y-4 text-sm text-white/55">
                <li>
                    <span class="block text-xs uppercase tracking-wider text-white/35">Almacén y oficinas</span>
                    Monterrey, Nuevo León
                </li>
                <li>
                    <span class="block text-xs uppercase tracking-wider text-white/35">Teléfono</span>
                    <a href="tel:+528181000000" class="transition hover:text-brand-400">81 8100 0000</a>
                </li>
                <li>
                    <span class="block text-xs uppercase tracking-wider text-white/35">Ventas</span>
                    <a href="mailto:{{ config('icce.sales_email') }}" class="transition hover:text-brand-400">{{ config('icce.sales_email') }}</a>
                </li>
            </ul>
        </div>
    </x-ui.container>

    <div class="border-t border-white/10">
        <x-ui.container class="flex flex-col gap-3 py-6 text-xs text-white/40 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ now()->year }} ICCE Rentas y Servicios. Todos los derechos reservados.</p>
            <p class="flex gap-5">
                <a href="/aviso-de-privacidad" class="transition hover:text-white/70">Aviso de privacidad</a>
                <a href="/politicas" class="transition hover:text-white/70">Políticas</a>
            </p>
        </x-ui.container>
    </div>
</footer>
