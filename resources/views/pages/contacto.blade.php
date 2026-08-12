<x-layouts.app title="Contacto"
    description="Almacén y oficinas en Monterrey, Nuevo León. Atención por teléfono, correo y WhatsApp Ventas.">

    <x-ui.page-header
        eyebrow="Estamos para ayudarte"
        title="Contacto y atención"
        lead="Cuéntanos qué necesitas: producto, cantidad y fecha de obra. Te respondemos el mismo día hábil."
        :breadcrumbs="['Contacto' => null]" />

    <section class="py-16 lg:py-24">
        <x-ui.container class="grid gap-14 lg:grid-cols-5 lg:gap-20">
            {{-- 6.1 Formulario general --}}
            <div class="lg:col-span-3">
                @if($enviado = session('contacto.enviado'))
                    <div class="rounded-2xl border border-brand-500/40 bg-brand-50 p-8">
                        <span class="flex size-12 items-center justify-center rounded-full bg-brand-500 text-carbon-950">
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path d="m5 13 4 4 10-11" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <h2 class="mt-5 font-display text-2xl font-extrabold text-carbon-950">
                            Gracias, {{ \App\Support\PersonName::first($enviado) }}
                        </h2>
                        <p class="mt-3 leading-relaxed text-carbon-700">
                            Tu mensaje ya está con el equipo de ventas. Te contactamos el mismo día hábil
                            y te enviamos una copia a tu correo.
                        </p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <x-ui.button href="{{ route('catalogo.index') }}">Ver el catálogo</x-ui.button>
                            <x-ui.button href="{{ route('contacto') }}" variant="outline" :icon="false">
                                Enviar otro mensaje
                            </x-ui.button>
                        </div>
                    </div>
                @else
                    <x-ui.section-heading eyebrow="Formulario general" title="Escríbenos" />

                    @if($errors->any())
                        <div class="mt-8 rounded-xl border border-red-300 bg-red-50 p-5">
                            <p class="font-display text-sm font-bold text-red-900">Revisa estos campos</p>
                            <ul class="mt-2 space-y-1 text-sm text-red-800">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('contacto.store') }}" method="post" class="mt-9 grid gap-5 sm:grid-cols-2">
                        @csrf

                        @foreach([
                            ['nombre', 'Nombre completo', 'text', true, false, auth()->user()?->name],
                            ['empresa', 'Empresa', 'text', false, false, auth()->user()?->company],
                            ['email', 'Correo electrónico', 'email', true, false, auth()->user()?->email],
                            ['telefono', 'Teléfono', 'tel', true, false, auth()->user()?->phone],
                            ['obra', 'Ubicación de la obra', 'text', false, true, null],
                        ] as [$name, $label, $type, $required, $full, $default])
                            <div @class(['sm:col-span-2' => $full])>
                                <label for="{{ $name }}" class="block text-sm font-semibold text-carbon-900">
                                    {{ $label }} @if($required)<span class="text-brand-700">*</span>@endif
                                </label>
                                <input type="{{ $type }}" id="{{ $name }}" name="{{ $name }}"
                                       value="{{ old($name, $default) }}" @required($required)
                                       @class([
                                           'mt-2 h-12 w-full rounded-lg border bg-white px-4 text-sm text-carbon-900 outline-none transition placeholder:text-carbon-400 focus:ring-2 focus:ring-brand-500/25',
                                           'border-carbon-300 focus:border-brand-500' => ! $errors->has($name),
                                           'border-red-400' => $errors->has($name),
                                       ])>
                                @error($name)<p class="mt-1.5 text-xs text-red-700">{{ $message }}</p>@enderror
                            </div>
                        @endforeach

                        <div class="sm:col-span-2">
                            <label for="asunto" class="block text-sm font-semibold text-carbon-900">
                                ¿En qué te ayudamos? <span class="text-brand-700">*</span>
                            </label>
                            <select id="asunto" name="asunto" required
                                    class="mt-2 h-12 w-full rounded-lg border border-carbon-300 bg-white px-4 text-sm text-carbon-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                                @foreach([
                                    'Cotización de producto',
                                    'Renta de equipo',
                                    'Asesoría técnica',
                                    'Seguimiento a un pedido',
                                    'Ficha técnica o documentación',
                                    'Otro',
                                ] as $opcion)
                                    <option @selected(old('asunto') === $opcion)>{{ $opcion }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <label for="mensaje" class="block text-sm font-semibold text-carbon-900">
                                Mensaje <span class="text-brand-700">*</span>
                            </label>
                            <textarea id="mensaje" name="mensaje" rows="5" required
                                      placeholder="Producto o equipo, cantidades y fecha en que lo necesitas."
                                      @class([
                                          'mt-2 w-full rounded-lg border bg-white p-4 text-sm text-carbon-900 outline-none transition placeholder:text-carbon-400 focus:ring-2 focus:ring-brand-500/25',
                                          'border-carbon-300 focus:border-brand-500' => ! $errors->has('mensaje'),
                                          'border-red-400' => $errors->has('mensaje'),
                                      ])>{{ old('mensaje') }}</textarea>
                            @error('mensaje')<p class="mt-1.5 text-xs text-red-700">{{ $message }}</p>@enderror
                        </div>

                        {{-- Trampa antispam: oculta para personas, visible para robots.
                             Fuera del flujo y sin foco por teclado, no la anuncia un lector de pantalla. --}}
                        <div class="hidden" aria-hidden="true">
                            <label for="apellido_materno">Apellido materno</label>
                            <input type="text" id="apellido_materno" name="apellido_materno" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="flex items-start gap-2.5 text-sm text-carbon-600">
                                <input type="checkbox" name="acepto" value="1" required @checked(old('acepto'))
                                       class="mt-0.5 size-4 shrink-0 rounded border-carbon-300 accent-brand-600 focus:ring-2 focus:ring-brand-500/40">
                                <span>
                                    Acepto que ICCE use mis datos para contactarme, según el
                                    <a href="{{ route('privacidad') }}" class="font-semibold text-brand-700 underline">aviso de privacidad</a>.
                                </span>
                            </label>
                            @error('acepto')<p class="mt-1.5 text-xs text-red-700">{{ $message }}</p>@enderror
                        </div>

                        <div class="sm:col-span-2">
                            <x-ui.button type="submit" size="lg">Enviar mensaje</x-ui.button>
                        </div>
                    </form>
                @endif
            </div>

            {{-- 6.1 Ubicación / 6.2 WhatsApp --}}
            <aside class="lg:col-span-2">
                <div class="rounded-2xl border border-carbon-200 bg-carbon-50 p-7">
                    <h2 class="font-display text-lg font-extrabold text-carbon-950">Almacén y oficinas</h2>
                    <dl class="mt-6 space-y-5 text-sm">
                        @foreach([
                            ['Dirección', 'Monterrey, Nuevo León, México', null],
                            ['Teléfono', '81 8100 0000', 'tel:+528181000000'],
                            ['Ventas', config('icce.sales_email'), 'mailto:'.config('icce.sales_email')],
                            ['Horario', 'Lunes a viernes, 8:00 a 18:00 h', null],
                        ] as [$label, $value, $href])
                            <div>
                                <dt class="text-xs uppercase tracking-wider text-carbon-400">{{ $label }}</dt>
                                <dd class="mt-1 font-medium text-carbon-900">
                                    @if($href)
                                        <a href="{{ $href }}" class="transition hover:text-brand-700">{{ $value }}</a>
                                    @else
                                        {{ $value }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>

                    <a href="https://g.page/ICCERYS?share" target="_blank" rel="noopener noreferrer"
                       class="mt-7 flex items-center gap-2 text-sm font-semibold text-brand-700 transition hover:text-brand-800">
                        Ver ubicación en Google Maps
                        <svg class="size-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <path d="M3 8h10M9 4l4 4-4 4" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                </div>

                <div class="mt-4 rounded-2xl border border-carbon-200 p-7">
                    <h2 class="font-display text-lg font-extrabold text-carbon-950">¿Necesitas rentar equipo?</h2>
                    <p class="mt-2 text-sm leading-relaxed text-carbon-600">
                        Hay un formulario específico que pregunta lo que el equipo de renta necesita
                        saber para confirmarte disponibilidad y tarifa.
                    </p>
                    <x-ui.button href="{{ route('renta.solicitar') }}" variant="outline" class="mt-5 w-full">
                        Solicitar renta
                    </x-ui.button>
                </div>
            </aside>
        </x-ui.container>
    </section>
</x-layouts.app>
