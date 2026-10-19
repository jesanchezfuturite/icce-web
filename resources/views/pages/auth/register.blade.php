<x-layouts.app title="Crear cuenta" description="Crea tu cuenta de cliente para cotizar, comprar y dar seguimiento a tus pedidos de ICCE.">

    <section class="relative isolate overflow-hidden bg-carbon-950 py-20 lg:py-28">
        <img src="{{ asset('images/proyectos/MedicionDePlanicidad.jpg') }}" alt=""
             class="absolute inset-0 size-full object-cover opacity-25">
        <div class="absolute inset-0 bg-gradient-to-t from-carbon-950 via-carbon-950/85 to-carbon-950/60"></div>

        <x-ui.container class="relative max-w-md">
            <div class="rounded-2xl border border-white/10 bg-carbon-900/80 p-8 backdrop-blur-xl sm:p-10">
                <x-ui.eyebrow tone="light">Portal de cliente</x-ui.eyebrow>

                <h1 class="mt-5 font-display text-3xl font-extrabold leading-tight text-white">Crea tu cuenta</h1>
                <p class="mt-3 text-sm leading-relaxed text-white/55">
                    Con tu cuenta cotizas y compras en línea, y sigues tus pedidos y su fecha de entrega.
                </p>

                @if($errors->any())
                    <div class="mt-6 rounded-lg border border-red-500/40 bg-red-500/10 p-4 text-sm text-red-200" role="alert">
                        <ul class="space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="post" action="{{ route('register') }}" class="mt-8 space-y-5"
                      x-data="{ enviando: false }" @submit="enviando = true" @pageshow.window="enviando = false">
                    @csrf

                    @foreach([
                        ['name', 'Nombre completo', 'text', true, 'name'],
                        ['email', 'Correo electrónico', 'email', true, 'username'],
                        ['phone', 'Teléfono', 'tel', false, 'tel'],
                        ['company', 'Empresa', 'text', false, 'organization'],
                    ] as [$name, $label, $type, $required, $autocomplete])
                        <div>
                            <label for="{{ $name }}" class="block text-sm font-semibold text-white">
                                {{ $label }} @unless($required)<span class="font-normal text-white/40">(opcional)</span>@endunless
                            </label>
                            <input type="{{ $type }}" id="{{ $name }}" name="{{ $name }}" value="{{ old($name) }}"
                                   @required($required) autocomplete="{{ $autocomplete }}" @if($loop->first) autofocus @endif
                                   class="mt-2 h-12 w-full rounded-lg border border-white/15 bg-white/5 px-4 text-sm text-white outline-none transition placeholder:text-white/30 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                        </div>
                    @endforeach

                    <div>
                        <label for="password" class="block text-sm font-semibold text-white">Contraseña <span class="font-normal text-white/40">(mínimo 8 caracteres)</span></label>
                        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password"
                               class="mt-2 h-12 w-full rounded-lg border border-white/15 bg-white/5 px-4 text-sm text-white outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-semibold text-white">Confirma tu contraseña</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password"
                               class="mt-2 h-12 w-full rounded-lg border border-white/15 bg-white/5 px-4 text-sm text-white outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                    </div>

                    <x-ui.button type="submit" size="lg" class="w-full" x-bind:disabled="enviando">
                        <span x-show="! enviando">Crear mi cuenta</span>
                        <span x-show="enviando" x-cloak>Creando cuenta…</span>
                    </x-ui.button>

                    <p class="text-xs leading-relaxed text-white/40">
                        Al crear tu cuenta aceptas el
                        <a href="{{ route('privacidad') }}" class="underline transition hover:text-white/70">aviso de privacidad</a>.
                    </p>
                </form>

                <p class="mt-8 border-t border-white/10 pt-6 text-sm text-white/50">
                    ¿Ya tienes cuenta?
                    <a href="{{ route('login') }}" class="font-semibold text-brand-400 transition hover:text-brand-300">Inicia sesión</a>.
                </p>
            </div>
        </x-ui.container>
    </section>
</x-layouts.app>
