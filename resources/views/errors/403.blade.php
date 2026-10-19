<x-layouts.app title="Acceso restringido" description="No tienes permiso para ver esta página.">

    <section class="py-20 lg:py-28">
        <x-ui.container class="max-w-xl text-center">
            <p class="font-display text-sm font-bold uppercase tracking-[0.18em] text-brand-700">Error 403</p>
            <h1 class="mt-4 font-display text-3xl font-extrabold leading-tight text-carbon-950 sm:text-4xl">
                No tienes permiso para ver esta página
            </h1>

            @auth
                <p class="mt-5 leading-relaxed text-carbon-600">
                    Entraste con <strong class="font-semibold text-carbon-900">{{ auth()->user()->email }}</strong>,
                    una cuenta de <strong class="font-semibold text-carbon-900">{{ auth()->user()->role->label() }}</strong>.
                    Esta sección es del personal de ICCE.
                </p>
                <p class="mt-3 text-sm leading-relaxed text-carbon-500">
                    Si eres parte del equipo y llegaste aquí desde una sesión de cliente,
                    cierra sesión y vuelve a entrar con tu cuenta de personal.
                </p>

                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <x-ui.button type="submit" :icon="false">Cerrar sesión</x-ui.button>
                    </form>
                    <x-ui.button :href="route('portal.index')" variant="outline" :icon="false">Ir a mi cuenta</x-ui.button>
                </div>
            @else
                <p class="mt-5 leading-relaxed text-carbon-600">
                    Inicia sesión con una cuenta que tenga acceso a esta sección.
                </p>

                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    <x-ui.button :href="route('login')" :icon="false">Iniciar sesión</x-ui.button>
                    <x-ui.button :href="route('home')" variant="outline" :icon="false">Ir al inicio</x-ui.button>
                </div>
            @endauth
        </x-ui.container>
    </section>
</x-layouts.app>
