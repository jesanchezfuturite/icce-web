@props(['post'])

<article class="group flex flex-col">
    <a href="{{ route('blog.show', $post) }}" class="block overflow-hidden rounded-xl bg-carbon-100">
        <div class="aspect-16/10 overflow-hidden">
            @if($post->cover_image)
                <img src="{{ asset($post->cover_image) }}" alt="{{ $post->title }}" loading="lazy"
                     class="size-full object-cover transition duration-500 group-hover:scale-105">
            @endif
        </div>
    </a>

    <div class="flex flex-1 flex-col pt-5">
        <p class="flex items-center gap-3 text-xs font-semibold uppercase tracking-wider text-carbon-400">
            @if($post->topic)<span class="text-brand-700">{{ $post->topic }}</span><span class="text-carbon-300">&middot;</span>@endif
            <span>{{ $post->reading_minutes }} min de lectura</span>
        </p>

        <h3 class="mt-3 font-display text-lg font-bold leading-snug text-carbon-950">
            <a href="{{ route('blog.show', $post) }}" class="transition hover:text-brand-700">{{ $post->title }}</a>
        </h3>

        <p class="mt-2 line-clamp-3 text-sm leading-relaxed text-carbon-600">{{ $post->excerpt }}</p>

        <p class="mt-4 text-xs text-carbon-400">
            {{ $post->published_at?->translatedFormat('d \d\e F, Y') }}
        </p>
    </div>
</article>
