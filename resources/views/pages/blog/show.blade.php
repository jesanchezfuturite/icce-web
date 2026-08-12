<x-layouts.app :title="$post->title" :description="$post->meta_description ?? $post->excerpt" :image="$post->cover_image">

    <x-ui.page-header
        :eyebrow="$post->topic"
        :title="$post->title"
        :image="$post->cover_image"
        :breadcrumbs="['Blog técnico' => route('blog.index'), $post->title => null]">

        <p class="mt-7 flex flex-wrap items-center gap-3 text-sm text-white/50">
            @if($post->author)<span>Por {{ $post->author->name }}</span><span class="text-white/25">&middot;</span>@endif
            <span>{{ $post->published_at?->translatedFormat('d \d\e F, Y') }}</span>
            <span class="text-white/25">&middot;</span>
            <span>{{ $post->reading_minutes }} min de lectura</span>
        </p>
    </x-ui.page-header>

    <article class="py-16 lg:py-24">
        <x-ui.container size="narrow">
            @if($post->excerpt)
                <p class="border-l-2 border-brand-500 pl-6 font-display text-lg font-medium leading-relaxed text-carbon-800 sm:text-xl">
                    {{ $post->excerpt }}
                </p>
            @endif

            {{-- El cuerpo llega del CMS en Markdown ligero; se escapa antes de convertir --}}
            <div class="prose-icce mt-10">
                @foreach(preg_split('/\n\s*\n/', (string) $post->body) as $block)
                    @php $block = trim($block); @endphp
                    @continue($block === '')

                    @if(str_starts_with($block, '## '))
                        <h2 class="mt-12 font-display text-2xl font-extrabold text-carbon-950">{{ substr($block, 3) }}</h2>
                    @else
                        <p class="mt-5 leading-relaxed text-carbon-700">{{ $block }}</p>
                    @endif
                @endforeach
            </div>

            <div class="mt-14 rounded-2xl border border-carbon-200 bg-carbon-50 p-7">
                <h2 class="font-display text-lg font-extrabold text-carbon-950">¿Necesitas resolver esto en obra?</h2>
                <p class="mt-2 text-sm leading-relaxed text-carbon-600">
                    Te ayudamos a elegir el producto y el equipo adecuados para tu losa y tus tolerancias.
                </p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <x-ui.button href="{{ route('contacto') }}">Hablar con un asesor</x-ui.button>
                    <x-ui.button href="{{ route('catalogo.index') }}" variant="outline" :icon="false">Ver catálogo</x-ui.button>
                </div>
            </div>
        </x-ui.container>
    </article>

    @if($related->isNotEmpty())
        <section class="border-t border-carbon-200 py-16 lg:py-20">
            <x-ui.container>
                <x-ui.section-heading eyebrow="Seguir leyendo" title="Del mismo tema" />
                <div class="mt-10 grid gap-10 sm:grid-cols-2">
                    @foreach($related as $item)
                        <x-cards.post :post="$item" />
                    @endforeach
                </div>
            </x-ui.container>
        </section>
    @endif
</x-layouts.app>
