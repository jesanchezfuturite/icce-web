@php
    $breadcrumbs = ['Catálogo' => route('catalogo.index')];
    if ($category->parent) {
        $breadcrumbs[$category->parent->name] = route('catalogo.categoria', $category->parent);
    }
    $breadcrumbs[$category->name] = null;
@endphp

<x-layouts.app :title="$category->name" :description="$category->meta_description ?? $category->description">

    <x-ui.page-header
        eyebrow="Catálogo"
        :title="$category->name"
        :lead="$category->description"
        :breadcrumbs="$breadcrumbs" />

    @if($category->children->isNotEmpty())
        <div class="border-b border-carbon-200 bg-carbon-50">
            <x-ui.container class="flex flex-wrap gap-2 py-5">
                @foreach($category->children as $child)
                    <a href="{{ route('catalogo.categoria', $child) }}"
                       class="rounded-full border border-carbon-300 bg-white px-4 py-1.5 text-xs font-semibold text-carbon-700 transition hover:border-carbon-950">
                        {{ $child->name }}
                    </a>
                @endforeach
            </x-ui.container>
        </div>
    @endif

    <section class="py-12 lg:py-16">
        <x-ui.container>
            <livewire:catalogo.explorador :category="$category" />
        </x-ui.container>
    </section>
</x-layouts.app>
