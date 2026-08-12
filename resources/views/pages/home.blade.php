<x-layouts.app
    description="Venta y renta de herramienta, maquinaria y materiales para pisos industriales de concreto. Distribuidor Somero, Kraft Tool y Husqvarna en México desde 1992.">

    {{-- 1.1 Banner principal --}}
    <section
        x-data="{
            active: 0,
            total: {{ max($banners->count(), 1) }},
            timer: null,
            start() { this.timer = setInterval(() => this.next(), 7000) },
            stop() { clearInterval(this.timer) },
            next() { this.active = (this.active + 1) % this.total },
            go(i) { this.active = i; this.stop(); this.start() },
        }"
        x-init="start()" @mouseenter="stop()" @mouseleave="start()"
        class="relative isolate min-h-[34rem] overflow-hidden bg-carbon-950 lg:min-h-[42rem]">

        @forelse($banners as $index => $banner)
            <div x-show="active === {{ $index }}" x-transition.opacity.duration.700ms class="absolute inset-0">
                <img src="{{ asset($banner->image_path) }}" alt="" class="size-full object-cover"
                     @if($index === 0) fetchpriority="high" @else loading="lazy" @endif>
                <div class="absolute inset-0 bg-gradient-to-r from-carbon-950 via-carbon-950/80 to-carbon-950/20"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-carbon-950/90 via-transparent to-transparent"></div>
            </div>
        @empty
            <div class="absolute inset-0 bg-carbon-900"></div>
        @endforelse

        <x-ui.container class="relative flex min-h-[34rem] flex-col justify-center py-20 lg:min-h-[42rem]">
            @foreach($banners as $index => $banner)
                <div x-show="active === {{ $index }}" x-cloak
                     x-transition:enter="transition duration-700 delay-100"
                     x-transition:enter-start="opacity-0 translate-y-4"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="max-w-2xl">

                    @if($banner->eyebrow)
                        <x-ui.eyebrow tone="light">{{ $banner->eyebrow }}</x-ui.eyebrow>
                    @endif

                    <h1 class="mt-5 font-display text-4xl font-extrabold leading-[1.03] text-white sm:text-5xl lg:text-6xl">
                        {{ $banner->title }}
                    </h1>

                    @if($banner->subtitle)
                        <p class="mt-5 max-w-xl text-base leading-relaxed text-white/70 sm:text-lg">{{ $banner->subtitle }}</p>
                    @endif

                    <div class="mt-9 flex flex-wrap gap-3">
                        @if($banner->cta_label)
                            <x-ui.button :href="$banner->cta_url" size="lg">{{ $banner->cta_label }}</x-ui.button>
                        @endif
                        @if($banner->secondary_cta_label)
                            <x-ui.button :href="$banner->secondary_cta_url" variant="outline-light" size="lg" :icon="false">
                                {{ $banner->secondary_cta_label }}
                            </x-ui.button>
                        @endif
                    </div>
                </div>
            @endforeach

            @if($banners->count() > 1)
                <div class="mt-14 flex items-center gap-3">
                    @foreach($banners as $index => $banner)
                        <button type="button" @click="go({{ $index }})"
                                class="h-1 rounded-full transition-all duration-300"
                                :class="active === {{ $index }} ? 'w-12 bg-brand-500' : 'w-6 bg-white/25 hover:bg-white/50'"
                                aria-label="Ir al banner {{ $index + 1 }}"></button>
                    @endforeach
                </div>
            @endif
        </x-ui.container>
    </section>

    {{-- Barra de credenciales --}}
    <section class="border-b border-carbon-200 bg-carbon-50">
        <x-ui.container class="grid grid-cols-2 divide-carbon-200 py-8 sm:divide-x lg:grid-cols-4">
            @foreach($stats as [$value, $label])
                <div class="px-2 py-4 text-center sm:px-6">
                    <p class="font-display text-2xl font-extrabold text-carbon-950 sm:text-3xl">{{ $value }}</p>
                    <p class="mt-1 text-xs uppercase tracking-wider text-carbon-500">{{ $label }}</p>
                </div>
            @endforeach
        </x-ui.container>
    </section>

    {{-- 1.2 Accesos rápidos por categoría --}}
    <section id="categorias" class="scroll-mt-28 py-20 lg:py-28">
        <x-ui.container>
            <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                <x-ui.section-heading
                    eyebrow="Catálogo"
                    title="Todo lo que pide un piso industrial, en un solo proveedor"
                    lead="Herramienta de acabado, químicos de especialidad y maquinaria en renta. Con existencia en almacén o bajo pedido programado." />
                <x-ui.button href="{{ route('catalogo.index') }}" variant="outline" class="shrink-0">Ver catálogo completo</x-ui.button>
            </div>

            <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($categories as $index => $category)
                    <a href="{{ route('catalogo.categoria', $category) }}"
                       @class([
                           'group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-carbon-200 bg-carbon-50 p-6 transition duration-200 hover:-translate-y-0.5 hover:border-carbon-950 hover:bg-white hover:shadow-xl hover:shadow-carbon-950/8',
                           'sm:col-span-2 lg:col-span-1 lg:row-span-2 lg:justify-end lg:bg-carbon-950 lg:p-8' => $index === 0,
                       ])>
                        @if($index === 0)
                            <img src="{{ asset('images/proyectos/Polishing.jpg') }}" alt="" loading="lazy"
                                 class="pointer-events-none absolute inset-0 hidden size-full object-cover opacity-35 transition duration-500 group-hover:scale-105 group-hover:opacity-45 lg:block">
                            <div class="pointer-events-none absolute inset-0 hidden bg-gradient-to-t from-carbon-950 via-carbon-950/70 to-transparent lg:block"></div>
                        @endif

                        <div class="relative">
                            <p @class([
                                'text-xs font-semibold uppercase tracking-wider',
                                'text-carbon-400' => $index !== 0,
                                'text-carbon-400 lg:text-brand-400' => $index === 0,
                            ])>{{ $category->totalProducts() }} productos</p>

                            <h3 @class([
                                'mt-2 font-display font-extrabold leading-tight text-carbon-950',
                                'text-xl' => $index !== 0,
                                'text-xl lg:text-3xl lg:text-white' => $index === 0,
                            ])>{{ $category->name }}</h3>

                            @if($category->description)
                                <p @class([
                                    'mt-3 text-sm leading-relaxed text-carbon-600',
                                    'lg:text-white/60' => $index === 0,
                                ])>{{ $category->description }}</p>
                            @endif
                        </div>

                        <span @class([
                            'relative mt-6 inline-flex items-center gap-2 text-sm font-semibold text-brand-700',
                            'lg:text-brand-400' => $index === 0,
                        ])>
                            Explorar
                            <svg class="size-4 transition-transform group-hover:translate-x-1" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                <path d="M3 8h10M9 4l4 4-4 4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </a>
                @endforeach
            </div>
        </x-ui.container>
    </section>

    {{-- 1.3 Destacado B2B: cotización masiva --}}
    <section id="b2b" class="scroll-mt-28 relative overflow-hidden bg-carbon-950 py-20 lg:py-28">
        <div class="scale-rule absolute inset-x-0 top-0 h-1 opacity-60"></div>
        <div class="pointer-events-none absolute -right-40 top-1/2 size-[36rem] -translate-y-1/2 rounded-full bg-brand-500/10 blur-3xl"></div>

        <x-ui.container class="relative grid gap-14 lg:grid-cols-2 lg:items-center">
            <div>
                <x-ui.section-heading
                    tone="light"
                    eyebrow="Compras de volumen"
                    title="Pedidos grandes con precio de proyecto"
                    lead="Arriba de {{ config('icce.max_direct_purchase') }} unidades o en material bajo pedido, el carrito deja de cobrar y arma una solicitud de cotización. Un agente la revisa, ajusta el precio y te devuelve la propuesta con enlace de pago." />

                <div class="mt-9 flex flex-wrap gap-3">
                    <x-ui.button href="{{ route('contacto') }}" size="lg">Solicitar cotización</x-ui.button>
                    <x-ui.button href="{{ route('catalogo.index') }}" variant="outline-light" size="lg" :icon="false">Armar mi lista</x-ui.button>
                </div>
            </div>

            <ol class="relative space-y-px">
                @foreach([
                    ['Arma tu lista', 'Agrega productos y cantidades desde el catálogo, con o sin existencia.'],
                    ['Recibe tu folio', 'La solicitud entra al CRM con folio COT-'.now()->year.'-00000 y agente asignado.'],
                    ['Propuesta ajustada', 'El agente aplica descuento por volumen y envía la cotización en PDF.'],
                    ['Aprueba y paga', 'Autorizas en línea con tarjeta o SPEI y la orden pasa a almacén.'],
                ] as $step => [$title, $copy])
                    <li class="group flex gap-5 rounded-xl border border-white/10 bg-white/[0.03] p-5 transition hover:border-brand-500/40 hover:bg-white/[0.06]">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full border border-brand-500/40 font-display text-sm font-bold text-brand-400">
                            {{ $step + 1 }}
                        </span>
                        <div>
                            <h3 class="font-display font-bold text-white">{{ $title }}</h3>
                            <p class="mt-1 text-sm leading-relaxed text-white/55">{{ $copy }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-ui.container>
    </section>

    {{-- Productos destacados --}}
    @if($featured->isNotEmpty())
        <section class="py-20 lg:py-28">
            <x-ui.container>
                <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                    <x-ui.section-heading eyebrow="Con existencia" title="Sale de almacén hoy" />
                    <x-ui.button href="{{ route('catalogo.index') }}" variant="ghost" class="shrink-0">Ver todo</x-ui.button>
                </div>

                <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($featured as $product)
                        <x-cards.product :product="$product" />
                    @endforeach
                </div>
            </x-ui.container>
        </section>
    @endif

    {{-- 1.4 Carrusel de marcas --}}
    <section id="marcas" class="scroll-mt-28 border-y border-carbon-200 bg-white py-14 lg:py-16">
        <x-ui.container>
            <p class="text-center text-xs font-semibold uppercase tracking-[0.18em] text-carbon-500">
                Distribución autorizada
            </p>
        </x-ui.container>

        {{-- Marquesina infinita: la lista se duplica para que el bucle no corte --}}
        <div class="group relative mt-9 overflow-hidden [mask-image:linear-gradient(90deg,transparent,black_8%,black_92%,transparent)]">
            <div class="flex w-max animate-marquee items-center gap-14 group-hover:[animation-play-state:paused]">
                @foreach($brands->concat($brands) as $brand)
                    <a href="{{ route('marcas.show', $brand) }}" class="shrink-0" title="{{ $brand->name }}">
                        <img src="{{ asset($brand->logo_path) }}" alt="{{ $brand->name }}" loading="lazy"
                             class="h-11 w-auto opacity-45 grayscale transition duration-300 hover:opacity-100 hover:grayscale-0 sm:h-12">
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 1.5 Proyectos recientes --}}
    @if($projects->isNotEmpty())
        <section class="py-20 lg:py-28">
            <x-ui.container>
                <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                    <x-ui.section-heading
                        eyebrow="Casos de éxito"
                        title="Obras donde ya estuvimos"
                        lead="Naves industriales, centros de distribución y plantas de manufactura con piso superplano certificado." />
                    <x-ui.button href="{{ route('proyectos.index') }}" variant="outline" class="shrink-0">Ver todos los proyectos</x-ui.button>
                </div>

                {{-- Retícula bento: el proyecto destacado ocupa dos columnas y las
                     dos filas; los otros dos se apilan a su derecha. --}}
                <div class="mt-12 grid gap-4 lg:grid-cols-3 lg:grid-rows-2">
                    @foreach($projects as $index => $project)
                        <x-cards.project :project="$project" :featured="$index === 0"
                                         :class="$index === 0 ? 'lg:col-span-2 lg:row-span-2' : ''" />
                    @endforeach
                </div>
            </x-ui.container>
        </section>
    @endif

    {{-- 1.6 Blog técnico --}}
    @if($posts->isNotEmpty())
        <section id="blog" class="scroll-mt-28 border-t border-carbon-200 py-20 lg:py-28">
            <x-ui.container>
                <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                    <x-ui.section-heading
                        eyebrow="Blog técnico"
                        title="Criterio de aplicación, no folletos"
                        lead="Lo que aprendimos resolviendo pisos en obra: tolerancias, juntas, reparación y curado." />
                    <x-ui.button href="{{ route('blog.index') }}" variant="ghost" class="shrink-0">Todos los artículos</x-ui.button>
                </div>

                <div class="mt-12 grid gap-10 md:grid-cols-3">
                    @foreach($posts as $post)
                        <x-cards.post :post="$post" />
                    @endforeach
                </div>
            </x-ui.container>
        </section>
    @endif
</x-layouts.app>
