@php
    // El compositor de vistas entrega arreglos de primitivas, no modelos (ver
    // AppServiceProvider::shareNavigation y la nota en config/cache.php).
    $navCategories = collect($navCategories ?? []);
    $catalog = $navCategories->where('slug', '!=', 'renta-de-equipos');

    $links = [
        ['label' => 'Empresa', 'url' => '/empresa'],
        ['label' => 'Renta de equipos', 'url' => '/renta'],
        ['label' => 'Proyectos', 'url' => '/proyectos'],
        ['label' => 'Blog técnico', 'url' => '/blog'],
        ['label' => 'Contacto', 'url' => '/contacto'],
    ];
@endphp

<header x-data="{ mobile: false, mega: false, buscar: false }" class="sticky top-0 z-50"
        @keydown.escape.window="buscar = false; mobile = false">
    {{-- Barra superior: credenciales y contacto directo --}}
    <div class="hidden bg-carbon-950 text-white/60 lg:block">
        <x-ui.container class="flex h-9 items-center justify-between text-xs">
            <p class="flex items-center gap-2">
                <span class="size-1.5 rounded-full bg-brand-500"></span>
                Distribuidor autorizado Somero&nbsp;&middot;&nbsp;Kraft Tool&nbsp;&middot;&nbsp;Husqvarna &mdash; desde 1992
            </p>
            <div class="flex items-center gap-6">
                <a href="tel:+528181000000" class="transition hover:text-white">81 8100 0000</a>
                <a href="mailto:{{ config('icce.sales_email') }}" class="transition hover:text-white">{{ config('icce.sales_email') }}</a>
                @auth
                    <a href="{{ auth()->user()->role->canAccessAdminPanel() ? '/admin' : route('portal.index') }}"
                       class="font-semibold text-white/80 transition hover:text-brand-400">
                        {{ auth()->user()->role->canAccessAdminPanel() ? 'Backoffice' : 'Mi cuenta' }}
                    </a>
                    <form method="post" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="transition hover:text-white">Salir</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="font-semibold text-white/80 transition hover:text-brand-400">Mi cuenta</a>
                @endauth
            </div>
        </x-ui.container>
    </div>

    {{-- Navegación principal --}}
    <div class="border-b border-white/10 bg-carbon-900/95 backdrop-blur-xl">
        <x-ui.container class="flex h-18 items-center justify-between gap-6 py-4">
            <a href="/" class="shrink-0" aria-label="ICCE Rentas y Servicios, inicio">
                <img src="{{ asset('images/marca/logo-icce.png') }}" alt="ICCE Rentas y Servicios"
                     class="h-7 w-auto sm:h-8" width="412" height="51">
            </a>

            <nav class="hidden items-center gap-1 lg:flex" aria-label="Principal">
                {{-- Catálogo: mega menú --}}
                <div @mouseenter="mega = true" @mouseleave="mega = false" class="relative">
                    <a href="/catalogo"
                       class="flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold text-white/85 transition hover:bg-white/10 hover:text-white"
                       :class="mega && 'bg-white/10 text-white'">
                        Catálogo
                        <svg class="size-3.5 transition-transform" :class="mega && 'rotate-180'"
                             viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="m4 6 4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>

                    <div x-show="mega" x-cloak x-transition.opacity.duration.150ms
                         class="absolute left-1/2 top-full w-[54rem] -translate-x-1/2 pt-3">
                        <div class="overflow-hidden rounded-2xl border border-carbon-200 bg-white shadow-2xl shadow-carbon-950/20">
                            <div class="grid grid-cols-3 gap-x-8 gap-y-7 p-8">
                                @foreach($catalog as $root)
                                    <div>
                                        <a href="/catalogo/{{ $root['slug'] }}"
                                           class="font-display text-sm font-bold uppercase tracking-wide text-carbon-950 hover:text-brand-700">
                                            {{ $root['name'] }}
                                        </a>
                                        @if($root['children'])
                                            <ul class="mt-3 space-y-1.5">
                                                @foreach($root['children'] as $child)
                                                    <li>
                                                        <a href="/catalogo/{{ $child['slug'] }}"
                                                           class="text-sm text-carbon-600 transition hover:text-brand-700">{{ $child['name'] }}</a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            <div class="flex items-center justify-between gap-4 border-t border-carbon-200 bg-carbon-50 px-8 py-5">
                                <p class="text-sm text-carbon-600">
                                    <span class="font-semibold text-carbon-950">¿Pedido de volumen?</span>
                                    Arma tu lista y recibe una cotización con precio de proyecto.
                                </p>
                                <x-ui.button href="/cotizacion" size="sm">Solicitar cotización</x-ui.button>
                            </div>
                        </div>
                    </div>
                </div>

                @foreach($links as $link)
                    <a href="{{ $link['url'] }}"
                       @class([
                           'rounded-full px-4 py-2 text-sm font-semibold transition hover:bg-white/10 hover:text-white',
                           'bg-white/10 text-white' => request()->is(ltrim($link['url'], '/').'*'),
                           'text-white/85' => ! request()->is(ltrim($link['url'], '/').'*'),
                       ])>{{ $link['label'] }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-2">
                <button type="button" @click="buscar = !buscar; $nextTick(() => $refs.buscador?.focus())"
                        class="rounded-full p-2.5 text-white/70 transition hover:bg-white/10 hover:text-white"
                        :class="buscar && 'bg-white/10 text-white'"
                        aria-label="Buscar productos" :aria-expanded="buscar">
                    <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <circle cx="9" cy="9" r="6"/><path d="m13.5 13.5 3.5 3.5" stroke-linecap="round"/>
                    </svg>
                </button>

                <livewire:carrito.contador />

                <x-ui.button href="/contacto" size="sm" class="hidden xl:inline-flex">Cotizar</x-ui.button>

                <button type="button" @click="mobile = true"
                        class="rounded-full p-2.5 text-white transition hover:bg-white/10 lg:hidden" aria-label="Abrir menú">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
        </x-ui.container>

        {{-- Buscador global: envía por GET al catálogo, así el resultado
             queda en una URL compartible e indexable. --}}
        <div x-show="buscar" x-cloak x-collapse.duration.200ms class="border-t border-white/10 bg-carbon-950">
            <x-ui.container class="py-5">
                <form action="{{ route('catalogo.index') }}" method="get" class="flex gap-3">
                    <div class="relative flex-1">
                        <svg class="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-white/40"
                             viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <circle cx="9" cy="9" r="6"/><path d="m13.5 13.5 3.5 3.5" stroke-linecap="round"/>
                        </svg>
                        <input type="search" name="q" x-ref="buscador" value="{{ request('q') }}"
                               placeholder="Busca por nombre, SKU o marca — llana, KRASK401, Somero…"
                               aria-label="Buscar en el catálogo"
                               class="h-12 w-full rounded-full border border-white/15 bg-white/5 pl-11 pr-4 text-sm text-white outline-none transition placeholder:text-white/35 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                    </div>
                    <x-ui.button type="submit" :icon="false">Buscar</x-ui.button>
                </form>
            </x-ui.container>
        </div>

        {{-- Motivo de escala: guiño a la regla de nivelación láser --}}
        <div class="scale-rule h-1 w-full opacity-70"></div>
    </div>

    {{-- Menú móvil --}}
    <div x-show="mobile" x-cloak class="fixed inset-0 z-50 lg:hidden">
        <div x-show="mobile" x-transition.opacity class="absolute inset-0 bg-carbon-950/70 backdrop-blur-sm" @click="mobile = false"></div>

        <div x-show="mobile"
             x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
             class="absolute inset-y-0 right-0 flex w-full max-w-sm flex-col overflow-y-auto bg-carbon-950 text-white">
            <div class="flex items-center justify-between border-b border-white/10 px-5 py-4">
                <img src="{{ asset('images/marca/logo-icce.png') }}" alt="ICCE" class="h-7 w-auto">
                <button type="button" @click="mobile = false" class="rounded-full p-2 hover:bg-white/10" aria-label="Cerrar menú">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path d="m6 6 12 12M18 6 6 18" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <nav class="flex-1 px-5 py-6" aria-label="Menú móvil">
                <p class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-white/40">Catálogo</p>
                <ul class="mb-8 space-y-1">
                    @foreach($navCategories as $root)
                        <li>
                            <a href="/catalogo/{{ $root['slug'] }}"
                               class="block rounded-lg px-3 py-2.5 font-display font-semibold transition hover:bg-white/10">{{ $root['name'] }}</a>
                        </li>
                    @endforeach
                </ul>

                <p class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-white/40">Secciones</p>
                <ul class="space-y-1">
                    @foreach($links as $link)
                        <li>
                            <a href="{{ $link['url'] }}"
                               class="block rounded-lg px-3 py-2.5 text-white/80 transition hover:bg-white/10 hover:text-white">{{ $link['label'] }}</a>
                        </li>
                    @endforeach
                    @auth
                        <li><a href="{{ route('portal.index') }}" class="block rounded-lg px-3 py-2.5 text-white/80 transition hover:bg-white/10 hover:text-white">Mi cuenta</a></li>
                        <li>
                            <form method="post" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full rounded-lg px-3 py-2.5 text-left text-white/80 transition hover:bg-white/10 hover:text-white">Salir</button>
                            </form>
                        </li>
                    @else
                        <li><a href="{{ route('login') }}" class="block rounded-lg px-3 py-2.5 text-white/80 transition hover:bg-white/10 hover:text-white">Ingresar</a></li>
                    @endauth
                </ul>
            </nav>

            <div class="border-t border-white/10 p-5">
                <x-ui.button href="/contacto" class="w-full">Solicitar cotización</x-ui.button>
            </div>
        </div>
    </div>
</header>
