<?php

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Centro de descargas (5.2). Lista las fichas técnicas y hojas de seguridad
 * asociadas a los productos, con búsqueda y filtro por marca.
 */
new class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'marca', except: '')]
    public string $brandSlug = '';

    #[Computed]
    public function documents(): LengthAwarePaginator
    {
        return Product::query()
            ->active()
            ->where(function (Builder $q) {
                $q->whereNotNull('tech_sheet_pdf')->orWhereNotNull('safety_sheet_pdf');
            })
            ->when($this->brandSlug !== '',
                fn (Builder $q) => $q->whereHas('brand', fn (Builder $b) => $b->where('slug', $this->brandSlug)))
            ->search($this->search)
            ->with(['brand', 'category'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    /** Sólo las marcas que efectivamente tienen documentación publicada. */
    #[Computed]
    public function brands(): Collection
    {
        return Brand::query()
            ->whereHas('products', fn (Builder $q) => $q->where('is_active', true)->whereNotNull('tech_sheet_pdf'))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function total(): int
    {
        return Product::active()->whereNotNull('tech_sheet_pdf')->count();
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }
};
?>

<div>
    <div class="flex flex-col gap-4 sm:flex-row">
        <div class="relative flex-1">
            <svg class="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-carbon-400"
                 viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <circle cx="9" cy="9" r="6"/><path d="m13.5 13.5 3.5 3.5" stroke-linecap="round"/>
            </svg>
            <input type="search" wire:model.live.debounce.350ms="search"
                   placeholder="Buscar ficha por producto, SKU o marca…" aria-label="Buscar documentación"
                   class="h-12 w-full rounded-full border border-carbon-300 bg-white pl-11 pr-4 text-sm text-carbon-900 outline-none transition placeholder:text-carbon-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
        </div>

        <select wire:model.live="brandSlug" aria-label="Filtrar por marca"
                class="h-12 rounded-full border border-carbon-300 bg-white px-4 text-sm font-medium text-carbon-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
            <option value="">Todas las marcas</option>
            @foreach($this->brands() as $brand)
                <option value="{{ $brand->slug }}">{{ $brand->name }}</option>
            @endforeach
        </select>
    </div>

    <p class="mt-6 text-sm text-carbon-500" aria-live="polite">
        {{ $this->documents()->total() }} {{ Str::plural('documento', $this->documents()->total()) }}
        @if($this->search !== '') para «{{ $this->search }}» @endif
    </p>

    <div class="mt-6 divide-y divide-carbon-200 border-y border-carbon-200"
         wire:loading.class="opacity-40" wire:target="search,brandSlug,page">
        @forelse($this->documents() as $product)
            <div class="flex flex-wrap items-center gap-4 py-4" wire:key="d-{{ $product->id }}">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-700">
                    <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path d="M11.5 2H5.5A1.5 1.5 0 0 0 4 3.5v13A1.5 1.5 0 0 0 5.5 18h9a1.5 1.5 0 0 0 1.5-1.5V6.5L11.5 2Z" stroke-linejoin="round"/>
                        <path d="M11 2v5h5" stroke-linejoin="round"/>
                    </svg>
                </span>

                <div class="min-w-0 flex-1">
                    <a href="{{ $product->is_rental ? route('renta.equipo', $product) : route('producto', $product) }}"
                       class="block truncate text-sm font-semibold text-carbon-950 transition hover:text-brand-700">
                        {{ $product->name }}
                    </a>
                    <p class="mt-0.5 text-xs text-carbon-500">
                        @if($product->brand){{ $product->brand->name }} &middot; @endif
                        {{ $product->category?->name }} &middot; SKU {{ $product->sku }}
                    </p>
                </div>

                <div class="flex gap-2">
                    @if($product->tech_sheet_pdf)
                        <a href="{{ asset($product->tech_sheet_pdf) }}" target="_blank" rel="noopener"
                           class="rounded-full border border-carbon-300 px-4 py-1.5 text-xs font-semibold text-carbon-800 transition hover:border-carbon-950 hover:bg-carbon-50">
                            Ficha técnica
                        </a>
                    @endif
                    @if($product->safety_sheet_pdf)
                        <a href="{{ asset($product->safety_sheet_pdf) }}" target="_blank" rel="noopener"
                           class="rounded-full border border-carbon-300 px-4 py-1.5 text-xs font-semibold text-carbon-800 transition hover:border-carbon-950 hover:bg-carbon-50">
                            Hoja de seguridad
                        </a>
                    @endif
                </div>
            </div>
        @empty
            <div class="py-14 text-center">
                <p class="font-display text-lg font-bold text-carbon-950">Sin documentos</p>
                <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-carbon-600">
                    No encontramos documentación con esos criterios. Si necesitas la ficha de un
                    producto que no aparece, pídela a tu agente y la conseguimos con el fabricante.
                </p>
                <x-ui.button href="{{ route('contacto') }}" class="mt-6">Pedir una ficha</x-ui.button>
            </div>
        @endforelse
    </div>

    @if($this->documents()->hasPages())
        <div class="mt-12">{{ $this->documents()->links() }}</div>
    @endif
</div>
