@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => true,
])

@php
$base = 'group inline-flex items-center justify-center gap-2 font-semibold tracking-tight transition-all duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:opacity-50 disabled:pointer-events-none';

$variants = [
    // Verde de marca sobre carbón: el lima es demasiado claro para texto blanco
    'primary' => 'bg-brand-500 text-carbon-950 hover:bg-brand-400 shadow-[0_1px_0_0_var(--color-brand-700)] hover:shadow-[0_6px_22px_-6px_var(--color-brand-500)]',
    'dark' => 'bg-carbon-950 text-white hover:bg-carbon-800',
    'outline' => 'border border-carbon-300 text-carbon-900 hover:border-carbon-950 hover:bg-carbon-50',
    'outline-light' => 'border border-white/30 text-white hover:border-white hover:bg-white/10 backdrop-blur-sm',
    'ghost' => 'text-carbon-700 hover:text-carbon-950 hover:bg-carbon-100',
];

$sizes = [
    'sm' => 'h-9 px-4 text-sm rounded-full',
    'md' => 'h-11 px-6 text-sm rounded-full',
    'lg' => 'h-14 px-8 text-base rounded-full',
];

$classes = trim($base.' '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']));
$tag = $href ? 'a' : 'button';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->class($classes) }}>
    {{ $slot }}
    @if($icon)
        <svg class="size-4 shrink-0 transition-transform duration-200 group-hover:translate-x-0.5"
             viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
            <path d="M3 8h10M9 4l4 4-4 4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    @endif
</{{ $tag }}>
