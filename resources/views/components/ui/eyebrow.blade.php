@props(['tone' => 'dark'])

{{-- Etiqueta de sección con la regla verde de marca a la izquierda. --}}
<p {{ $attributes->class([
    'inline-flex items-center gap-3 text-xs font-semibold uppercase tracking-[0.18em]',
    'text-carbon-500' => $tone === 'dark',
    'text-white/70' => $tone === 'light',
]) }}>
    <span class="h-px w-8 bg-brand-500"></span>
    {{ $slot }}
</p>
