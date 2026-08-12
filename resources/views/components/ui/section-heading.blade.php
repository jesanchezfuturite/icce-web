@props([
    'eyebrow' => null,
    'title' => null,
    'lead' => null,
    'tone' => 'dark',
    'align' => 'left',
])

<div {{ $attributes->class([
    'flex flex-col gap-4',
    'items-center text-center' => $align === 'center',
    'max-w-2xl' => $align === 'left',
]) }}>
    @if($eyebrow)
        <x-ui.eyebrow :tone="$tone">{{ $eyebrow }}</x-ui.eyebrow>
    @endif

    @if($title)
        <h2 @class([
            'font-display text-3xl font-extrabold leading-[1.08] tracking-tight sm:text-4xl lg:text-[2.75rem]',
            'text-carbon-950' => $tone === 'dark',
            'text-white' => $tone === 'light',
        ])>{{ $title }}</h2>
    @endif

    @if($lead)
        <p @class([
            'text-base leading-relaxed sm:text-lg',
            'text-carbon-600' => $tone === 'dark',
            'text-white/65' => $tone === 'light',
        ])>{{ $lead }}</p>
    @endif

    {{ $slot }}
</div>
