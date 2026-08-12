<x-layouts.app title="Ingresar" description="Accede a tu portal de cliente para consultar pedidos, cotizaciones y fechas de entrega.">

    <section class="relative isolate overflow-hidden bg-carbon-950 py-20 lg:py-28">
        <img src="{{ asset('images/proyectos/MedicionDePlanicidad.jpg') }}" alt=""
             class="absolute inset-0 size-full object-cover opacity-25">
        <div class="absolute inset-0 bg-gradient-to-t from-carbon-950 via-carbon-950/85 to-carbon-950/60"></div>

        <x-ui.container class="relative max-w-md">
            <div class="rounded-2xl border border-white/10 bg-carbon-900/80 p-8 backdrop-blur-xl sm:p-10">
                <x-ui.eyebrow tone="light">Portal de cliente</x-ui.eyebrow>

                <h1 class="mt-5 font-display text-3xl font-extrabold leading-tight text-white">Ingresa a tu cuenta</h1>
                <p class="mt-3 text-sm leading-relaxed text-white/55">
                    Consulta el estatus de tus pedidos, tus cotizaciones y la fecha comprometida de entrega.
                </p>

                @if($errors->any())
                    <div class="mt-6 rounded-lg border border-red-500/40 bg-red-500/10 p-4 text-sm text-red-200">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="post" action="{{ route('login') }}" class="mt-8 space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-semibold text-white">Correo electrónico</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}"
                               required autofocus autocomplete="username"
                               class="mt-2 h-12 w-full rounded-lg border border-white/15 bg-white/5 px-4 text-sm text-white outline-none transition placeholder:text-white/30 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-white">Contraseña</label>
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                               class="mt-2 h-12 w-full rounded-lg border border-white/15 bg-white/5 px-4 text-sm text-white outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                    </div>

                    <label class="flex items-center gap-2.5 text-sm text-white/60">
                        <input type="checkbox" name="remember" value="1"
                               class="size-4 rounded border-white/20 bg-white/5 accent-brand-500 focus:ring-2 focus:ring-brand-500/40">
                        Mantener la sesión abierta
                    </label>

                    <x-ui.button type="submit" size="lg" class="w-full">Ingresar</x-ui.button>
                </form>

                <p class="mt-8 border-t border-white/10 pt-6 text-sm text-white/50">
                    ¿Aún no tienes cuenta?
                    <a href="{{ route('contacto') }}" class="font-semibold text-brand-400 transition hover:text-brand-300">
                        Solicítala a tu agente de ventas
                    </a>.
                </p>
            </div>

            <p class="mt-6 text-center text-xs text-white/35">
                ¿Eres parte del equipo de ICCE?
                <a href="/admin" class="underline transition hover:text-white/60">Entra al backoffice</a>.
            </p>
        </x-ui.container>
    </section>
</x-layouts.app>
