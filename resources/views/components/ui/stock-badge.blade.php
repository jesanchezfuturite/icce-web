@props(['product'])

@php
$label = $product->stockLabel();
$tone = match ($label) {
    'Disponible' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    'Últimas piezas' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
    'Bajo pedido' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
    default => 'bg-carbon-100 text-carbon-600 ring-carbon-500/20',
};
$dot = match ($label) {
    'Disponible' => 'bg-emerald-500',
    'Últimas piezas' => 'bg-amber-500',
    'Bajo pedido' => 'bg-sky-500',
    default => 'bg-carbon-400',
};
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset', $tone]) }}>
    <span class="size-1.5 rounded-full {{ $dot }}"></span>
    {{ $label }}
</span>
