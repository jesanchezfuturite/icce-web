{{--
    Una categoría del menú del catálogo, con sus subcategorías debajo.

    Si tiene subcategorías muestra una flecha: el primer clic (o toque) sobre su
    nombre las despliega y el segundo entra a la categoría. Si no tiene, un solo
    clic entra. La flecha por sí sola abre y cierra sin navegar.

    En escritorio las categorías principales (nivel 1) son el encabezado de su
    columna y enlazan directo, así que este componente se usa desde el nivel 2.
    En el celular todas se pliegan, incluidas las principales.
--}}
@props(['item', 'level' => 1, 'mobile' => false])

@php
    $hijas = $item['children'] ?? [];
    $esPadre = $hijas !== [];

    $enlace = $mobile
        ? ($level === 1
            ? 'block flex-1 rounded-lg px-3 py-2.5 font-display font-semibold transition hover:bg-white/10'
            : 'block flex-1 rounded-lg px-3 py-2 text-sm text-white/75 transition hover:bg-white/10 hover:text-white')
        : ($level === 2
            ? 'text-sm text-carbon-600 transition hover:text-brand-700'
            : 'text-xs text-carbon-500 transition hover:text-brand-700');

    $flecha = $mobile
        ? 'p-3 text-white/60 hover:text-white'
        : 'p-1 text-carbon-400 hover:text-brand-700';

    $anidado = $mobile
        ? 'ml-3 space-y-1 border-l border-white/10 pl-2'
        : 'mt-1 space-y-1 border-l border-carbon-200 pl-3';
@endphp

<li x-data="{ abierta: false }">
    <div class="flex items-center justify-between gap-1">
        <a href="/catalogo/{{ $item['slug'] }}" class="{{ $enlace }}"
           @if($esPadre) @click="if (! abierta) { $event.preventDefault(); abierta = true }" @endif>{{ $item['name'] }}</a>

        @if($esPadre)
            <button type="button" @click="abierta = ! abierta" :aria-expanded="abierta"
                    aria-label="Mostrar subcategorías de {{ $item['name'] }}"
                    class="shrink-0 rounded {{ $flecha }} transition">
                <svg class="size-3.5 transition-transform" :class="abierta && 'rotate-90'"
                     viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="m6 4 4 4-4 4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        @endif
    </div>

    @if($esPadre)
        <ul x-show="abierta" x-cloak x-collapse class="{{ $anidado }}">
            @foreach($hijas as $hija)
                <x-site.nav-item :item="$hija" :level="$level + 1" :mobile="$mobile" />
            @endforeach
        </ul>
    @endif
</li>
