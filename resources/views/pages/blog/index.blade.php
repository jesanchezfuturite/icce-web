<x-layouts.app title="Blog técnico"
    description="Artículos técnicos sobre diseño, colado, juntas, reparación y curado de pisos industriales de concreto.">

    <x-ui.page-header
        eyebrow="Blog técnico"
        title="Criterio de aplicación de concreto"
        lead="Lo que aprendimos resolviendo pisos en obra: tolerancias, juntas, refuerzo, reparación y curado."
        :breadcrumbs="['Blog técnico' => null]" />

    @if($topics->isNotEmpty())
        <div class="border-b border-carbon-200 bg-carbon-50">
            <x-ui.container class="flex flex-wrap gap-2 py-5">
                <span class="rounded-full bg-carbon-950 px-4 py-1.5 text-xs font-semibold text-white">Todos</span>
                @foreach($topics as $topic)
                    <span class="rounded-full border border-carbon-300 px-4 py-1.5 text-xs font-semibold text-carbon-600">{{ $topic }}</span>
                @endforeach
            </x-ui.container>
        </div>
    @endif

    @if($featured)
        <section class="py-16 lg:py-20">
            <x-ui.container>
                <a href="{{ route('blog.show', $featured) }}"
                   class="group grid gap-8 overflow-hidden rounded-2xl border border-carbon-200 transition hover:border-carbon-300 hover:shadow-xl hover:shadow-carbon-950/8 lg:grid-cols-2">
                    <div class="aspect-16/10 overflow-hidden bg-carbon-100 lg:aspect-auto">
                        @if($featured->cover_image)
                            <img src="{{ asset($featured->cover_image) }}" alt="{{ $featured->title }}"
                                 class="size-full object-cover transition duration-500 group-hover:scale-105">
                        @endif
                    </div>

                    <div class="flex flex-col justify-center p-8 lg:pr-12">
                        <x-ui.eyebrow>Artículo destacado</x-ui.eyebrow>
                        <h2 class="mt-4 font-display text-2xl font-extrabold leading-tight text-carbon-950 sm:text-3xl">
                            {{ $featured->title }}
                        </h2>
                        <p class="mt-4 leading-relaxed text-carbon-600">{{ $featured->excerpt }}</p>
                        <p class="mt-6 flex items-center gap-3 text-xs font-semibold uppercase tracking-wider text-carbon-400">
                            @if($featured->topic)<span class="text-brand-700">{{ $featured->topic }}</span><span class="text-carbon-300">&middot;</span>@endif
                            <span>{{ $featured->reading_minutes }} min</span>
                            <span class="text-carbon-300">&middot;</span>
                            <span>{{ $featured->published_at?->translatedFormat('d M Y') }}</span>
                        </p>
                    </div>
                </a>
            </x-ui.container>
        </section>
    @endif

    <section class="pb-24">
        <x-ui.container>
            <div class="grid gap-x-8 gap-y-14 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($posts as $post)
                    @continue($featured && $post->is($featured))
                    <x-cards.post :post="$post" />
                @endforeach
            </div>

            @if($posts->hasPages())
                <div class="mt-16">{{ $posts->links() }}</div>
            @endif
        </x-ui.container>
    </section>
</x-layouts.app>
