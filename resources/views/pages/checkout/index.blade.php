@php
    $needsPayment = $purchasable->isNotEmpty();
@endphp

<x-layouts.app title="Checkout" description="Confirma tus datos de entrega y método de pago." :hide-whatsapp="true">

    <x-ui.page-header
        eyebrow="Último paso"
        :title="$needsPayment ? 'Confirma y paga' : 'Confirma tu solicitud'"
        :lead="$needsPayment
            ? 'Captura tus datos de entrega. Sólo se cobra lo que tiene existencia; lo demás entra como cotización.'
            : 'Captura tus datos y un agente te envía la propuesta con precio de proyecto.'"
        :breadcrumbs="['Carrito' => route('carrito'), 'Checkout' => null]" />

    <section class="py-12 lg:py-16">
        <x-ui.container>
            @if($errors->any())
                <div class="mb-8 rounded-xl border border-red-300 bg-red-50 p-5">
                    <p class="font-display text-sm font-bold text-red-900">No pudimos completar el pedido</p>
                    <ul class="mt-2 space-y-1 text-sm text-red-800">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="post" action="{{ route('checkout.store') }}" class="grid gap-10 lg:grid-cols-[1fr_22rem] lg:gap-12">
                @csrf

                <div class="space-y-10">
                    {{-- Datos de contacto --}}
                    <fieldset>
                        <legend class="font-display text-lg font-extrabold text-carbon-950">Datos de contacto</legend>
                        <div class="mt-6 grid gap-5 sm:grid-cols-2">
                            @foreach([
                                ['nombre', 'Nombre completo', 'text', true, false, auth()->user()?->name],
                                ['email', 'Correo electrónico', 'email', true, false, auth()->user()?->email],
                                ['telefono', 'Teléfono', 'tel', true, false, auth()->user()?->phone],
                                ['empresa', 'Empresa', 'text', false, false, auth()->user()?->company],
                                ['rfc', 'RFC (para factura)', 'text', false, false, auth()->user()?->rfc],
                            ] as [$name, $label, $type, $required, $full, $default])
                                <div @class(['sm:col-span-2' => $full])>
                                    <label for="{{ $name }}" class="block text-sm font-semibold text-carbon-900">
                                        {{ $label }} @if($required)<span class="text-brand-700">*</span>@endif
                                    </label>
                                    <input type="{{ $type }}" id="{{ $name }}" name="{{ $name }}"
                                           value="{{ old($name, $default) }}" @required($required)
                                           @class([
                                               'mt-2 h-12 w-full rounded-lg border bg-white px-4 text-sm text-carbon-900 outline-none transition focus:ring-2 focus:ring-brand-500/25',
                                               'border-carbon-300 focus:border-brand-500' => ! $errors->has($name),
                                               'border-red-400' => $errors->has($name),
                                           ])>
                                    @error($name)<p class="mt-1.5 text-xs text-red-700">{{ $message }}</p>@enderror
                                </div>
                            @endforeach
                        </div>
                    </fieldset>

                    {{-- Dirección --}}
                    <fieldset>
                        <legend class="font-display text-lg font-extrabold text-carbon-950">Dirección de entrega</legend>
                        <div class="mt-6 grid gap-5 sm:grid-cols-2">
                            @foreach([
                                ['calle', 'Calle y número', true, true],
                                ['colonia', 'Colonia', false, false],
                                ['cp', 'Código postal', true, false],
                                ['ciudad', 'Ciudad', true, false],
                                ['estado', 'Estado', true, false],
                                ['referencias', 'Referencias de la obra', false, true],
                            ] as [$name, $label, $required, $full])
                                <div @class(['sm:col-span-2' => $full])>
                                    <label for="{{ $name }}" class="block text-sm font-semibold text-carbon-900">
                                        {{ $label }} @if($required)<span class="text-brand-700">*</span>@endif
                                    </label>
                                    <input type="text" id="{{ $name }}" name="{{ $name }}"
                                           value="{{ old($name) }}" @required($required)
                                           @class([
                                               'mt-2 h-12 w-full rounded-lg border bg-white px-4 text-sm text-carbon-900 outline-none transition focus:ring-2 focus:ring-brand-500/25',
                                               'border-carbon-300 focus:border-brand-500' => ! $errors->has($name),
                                               'border-red-400' => $errors->has($name),
                                           ])>
                                    @error($name)<p class="mt-1.5 text-xs text-red-700">{{ $message }}</p>@enderror
                                </div>
                            @endforeach

                            <div class="sm:col-span-2">
                                <label for="notas" class="block text-sm font-semibold text-carbon-900">Notas para el pedido</label>
                                <textarea id="notas" name="notas" rows="3"
                                          placeholder="Horario de recepción en obra, contacto en sitio, requerimientos especiales…"
                                          class="mt-2 w-full rounded-lg border border-carbon-300 bg-white p-4 text-sm text-carbon-900 outline-none transition placeholder:text-carbon-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">{{ old('notas') }}</textarea>
                            </div>
                        </div>
                    </fieldset>

                    {{-- Pago: sólo si hay algo cobrable (REQ-01) --}}
                    @if($needsPayment)
                        <fieldset x-data="{ metodo: '{{ old('metodo_pago', 'card') }}' }">
                            <legend class="font-display text-lg font-extrabold text-carbon-950">Método de pago</legend>

                            @if($gateway === 'simulado')
                                <p class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm leading-relaxed text-amber-900">
                                    <strong class="font-semibold">Pasarela en modo simulado.</strong>
                                    No se cobra nada real. Cualquier número de tarjeta se aprueba;
                                    usa <code class="rounded bg-amber-100 px-1">4000 0000 0000 0002</code> para
                                    provocar un rechazo y ver ese camino.
                                </p>
                            @endif

                            <div class="mt-6 space-y-3">
                                @foreach($methods as $value => $label)
                                    <label @class([
                                        'flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition',
                                        'border-carbon-300 hover:border-carbon-950',
                                    ])>
                                        <input type="radio" name="metodo_pago" value="{{ $value }}" x-model="metodo"
                                               class="size-4 border-carbon-300 accent-brand-600">
                                        <span class="text-sm font-semibold text-carbon-900">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <div x-show="metodo === 'card'" x-cloak class="mt-5">
                                <label for="tarjeta" class="block text-sm font-semibold text-carbon-900">
                                    Número de tarjeta <span class="text-brand-700">*</span>
                                </label>
                                <input type="text" id="tarjeta" name="tarjeta" value="{{ old('tarjeta') }}"
                                       inputmode="numeric" autocomplete="cc-number" placeholder="4242 4242 4242 4242"
                                       class="mt-2 h-12 w-full max-w-sm rounded-lg border border-carbon-300 bg-white px-4 text-sm text-carbon-900 outline-none transition placeholder:text-carbon-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                                @error('tarjeta')<p class="mt-1.5 text-xs text-red-700">{{ $message }}</p>@enderror
                            </div>

                            <p x-show="metodo === 'spei'" x-cloak class="mt-5 rounded-xl border border-carbon-200 bg-carbon-50 p-4 text-sm leading-relaxed text-carbon-600">
                                Al confirmar te enviamos la CLABE y la referencia. El pedido avanza a almacén
                                en cuanto se acredita la transferencia.
                            </p>
                        </fieldset>
                    @endif
                </div>

                {{-- Resumen --}}
                <aside class="lg:sticky lg:top-32 lg:self-start">
                    <div class="rounded-2xl border border-carbon-200 p-6">
                        <h2 class="font-display text-sm font-bold uppercase tracking-[0.16em] text-carbon-950">Tu pedido</h2>

                        @if($purchasable->isNotEmpty())
                            <div class="mt-5">
                                <p class="text-xs font-semibold uppercase tracking-wider text-brand-700">Se cobra hoy</p>
                                <ul class="mt-2 space-y-2 text-sm">
                                    @foreach($purchasable as $line)
                                        <li class="flex justify-between gap-3">
                                            <span class="min-w-0 flex-1 truncate text-carbon-600">{{ $line->quantity }} × {{ $line->product->name }}</span>
                                            <span class="tabular-nums text-carbon-900">${{ number_format($line->total(), 2) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                                <dl class="mt-4 space-y-2 border-t border-carbon-200 pt-4 text-sm">
                                    <div class="flex justify-between">
                                        <dt class="text-carbon-500">Subtotal</dt>
                                        <dd class="tabular-nums">${{ number_format($cart->subtotal($purchasable), 2) }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-carbon-500">IVA</dt>
                                        <dd class="tabular-nums">${{ number_format($cart->tax($purchasable), 2) }}</dd>
                                    </div>
                                    <div class="flex justify-between border-t border-carbon-200 pt-2">
                                        <dt class="font-display font-bold text-carbon-950">Total hoy</dt>
                                        <dd class="font-display text-lg font-extrabold tabular-nums text-carbon-950">
                                            ${{ number_format($cart->total($purchasable), 2) }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        @endif

                        @if($quotable->isNotEmpty())
                            <div @class(['mt-6 rounded-lg bg-sky-50 p-4', 'mt-0' => $purchasable->isEmpty()])>
                                <p class="text-xs font-semibold uppercase tracking-wider text-sky-800">Pasa a cotización</p>
                                <ul class="mt-2 space-y-1.5 text-sm">
                                    @foreach($quotable as $line)
                                        <li class="flex justify-between gap-3">
                                            <span class="min-w-0 flex-1 truncate text-sky-900">{{ $line->quantity }} × {{ $line->product->name }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                                <p class="mt-3 text-xs leading-relaxed text-sky-800">
                                    Sin cobro hoy. Un agente te envía la propuesta con precio de proyecto.
                                </p>
                            </div>
                        @endif

                        <x-ui.button type="submit" size="lg" class="mt-6 w-full">
                            {{ $needsPayment ? 'Confirmar y pagar' : 'Enviar solicitud' }}
                        </x-ui.button>

                        <p class="mt-4 text-xs leading-relaxed text-carbon-500">
                            Al confirmar aceptas el
                            <a href="{{ route('privacidad') }}" class="font-semibold text-brand-700 underline">aviso de privacidad</a>.
                        </p>
                    </div>

                    <a href="{{ route('carrito') }}"
                       class="mt-4 block text-center text-sm font-semibold text-brand-700 underline transition hover:text-brand-800">
                        Volver al carrito
                    </a>
                </aside>
            </form>
        </x-ui.container>
    </section>
</x-layouts.app>
