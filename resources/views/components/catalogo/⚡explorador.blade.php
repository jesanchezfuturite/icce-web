<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Explorador de catálogo (3.1 / 3.2): búsqueda, filtros y ordenamiento.
 *
 * Todo el estado viaja en la URL para que un resultado filtrado se pueda
 * compartir, marcar como favorito e indexar; es un requisito del RNF-03 tanto
 * como una comodidad de uso.
 */
new class extends Component
{
    use WithPagination;

    /** Categoría fijada por la ruta; null en /catalogo. */
    public ?int $categoryId = null;

    public bool $rentalsOnly = false;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** @var array<int, string> */
    #[Url(as: 'marca', except: [])]
    public array $brandSlugs = [];

    /** @var array<int, string> */
    #[Url(as: 'familia', except: [])]
    public array $categorySlugs = [];

    #[Url(as: 'disp', except: '')]
    public string $availability = '';

    #[Url(as: 'orden', except: 'relevancia')]
    public string $sort = 'relevancia';

    public function mount(?Category $category = null, bool $rentalsOnly = false): void
    {
        $this->categoryId = $category?->id;
        $this->rentalsOnly = $rentalsOnly;
    }

    /** Cualquier cambio de filtro debe devolver al usuario a la primera página. */
    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'brandSlugs', 'categorySlugs', 'availability', 'sort']);
        $this->resetPage();
    }

    public function removeBrand(string $slug): void
    {
        $this->brandSlugs = array_values(array_diff($this->brandSlugs, [$slug]));
        $this->resetPage();
    }

    public function removeCategory(string $slug): void
    {
        $this->categorySlugs = array_values(array_diff($this->categorySlugs, [$slug]));
        $this->resetPage();
    }

    #[Computed]
    public function hasFilters(): bool
    {
        return $this->search !== ''
            || $this->brandSlugs !== []
            || $this->categorySlugs !== []
            || $this->availability !== '';
    }

    /** IDs sobre los que opera este explorador: la rama fijada por la ruta. */
    #[Computed]
    public function scopeIds(): ?array
    {
        if ($this->categoryId === null) {
            return null;
        }

        return Category::findOrFail($this->categoryId)->descendantIds();
    }

    #[Computed]
    public function results(): LengthAwarePaginator
    {
        $query = Product::query()
            ->active()
            ->with(['brand', 'category', 'primaryImage'])
            ->when($this->rentalsOnly, fn (Builder $q) => $q->rentals())
            ->when($this->scopeIds(), fn (Builder $q, array $ids) => $q->whereIn('category_id', $ids))
            ->search($this->search);

        if ($this->brandSlugs !== []) {
            $query->whereHas('brand', fn (Builder $q) => $q->whereIn('slug', $this->brandSlugs));
        }

        if ($this->categorySlugs !== []) {
            $ids = Category::whereIn('slug', $this->categorySlugs)
                ->get()
                ->flatMap(fn (Category $c) => $c->descendantIds())
                ->unique()
                ->all();

            $query->whereIn('category_id', $ids);
        }

        match ($this->availability) {
            'disponible' => $query->directlyPurchasable(),
            'bajo-pedido' => $query->where('is_on_demand', true),
            'agotado' => $query->where('is_on_demand', false)->where('stock_qty', '<=', 0),
            default => null,
        };

        match ($this->sort) {
            'nombre' => $query->orderBy('name'),
            'precio-asc' => $query->orderBy('price'),
            'precio-desc' => $query->orderByDesc('price'),
            'existencia' => $query->orderByDesc('stock_qty')->orderBy('name'),
            default => $query->orderByRelevance($this->search),
        };

        return $query->paginate(24)->withQueryString();
    }

    /** Marcas presentes en la rama actual, con su conteo. */
    #[Computed]
    public function availableBrands(): Collection
    {
        return Brand::query()
            ->whereHas('products', function (Builder $q) {
                $q->where('is_active', true)
                    ->when($this->rentalsOnly, fn (Builder $r) => $r->where('is_rental', true))
                    ->when($this->scopeIds(), fn (Builder $r, array $ids) => $r->whereIn('category_id', $ids));
            })
            ->withCount(['products' => function (Builder $q) {
                $q->where('is_active', true)
                    ->when($this->rentalsOnly, fn (Builder $r) => $r->where('is_rental', true))
                    ->when($this->scopeIds(), fn (Builder $r, array $ids) => $r->whereIn('category_id', $ids));
            }])
            ->orderBy('name')
            ->get();
    }

    /** Subcategorías ofrecidas como filtro dentro de la rama actual. */
    #[Computed]
    public function availableCategories(): Collection
    {
        if ($this->categoryId !== null) {
            return Category::where('parent_id', $this->categoryId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        }

        return Category::roots()
            ->active()
            ->when($this->rentalsOnly,
                fn (Builder $q) => $q->where('slug', 'renta-de-equipos'),
                fn (Builder $q) => $q->where('slug', '!=', 'renta-de-equipos'))
            ->orderBy('sort_order')
            ->get();
    }
};
?>

<div class="grid gap-10 lg:grid-cols-[17rem_1fr] lg:gap-12">
    {{-- Panel de filtros --}}
    <aside x-data="{ open: false }" class="lg:sticky lg:top-32 lg:self-start">
        <button type="button" @click="open = !open"
                class="flex w-full items-center justify-between rounded-xl border border-carbon-300 px-5 py-3 text-sm font-semibold text-carbon-900 lg:hidden">
            Filtros
            @if($this->hasFilters())
                <span class="rounded-full bg-brand-500 px-2 py-0.5 text-xs text-carbon-950">activos</span>
            @endif
            <svg class="size-4 transition-transform" :class="open && 'rotate-180'" viewBox="0 0 16 16"
                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="m4 6 4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>

        <div x-show="open" x-cloak class="mt-4 lg:!block lg:mt-0" x-bind:class="{ 'hidden': !open }">
            <div class="space-y-8">
                {{-- Familias --}}
                @if($this->availableCategories()->isNotEmpty())
                    <fieldset>
                        <legend class="font-display text-sm font-bold uppercase tracking-[0.14em] text-carbon-950">
                            {{ $categoryId ? 'Subcategoría' : 'Familia' }}
                        </legend>
                        <div class="mt-4 space-y-2.5">
                            @foreach($this->availableCategories() as $option)
                                <label class="flex cursor-pointer items-center gap-2.5 text-sm text-carbon-600 transition hover:text-carbon-950">
                                    <input type="checkbox" wire:model.live="categorySlugs" value="{{ $option->slug }}"
                                           class="size-4 rounded border-carbon-300 accent-brand-600 focus:ring-2 focus:ring-brand-500/40">
                                    {{ $option->name }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                {{-- Disponibilidad --}}
                <fieldset>
                    <legend class="font-display text-sm font-bold uppercase tracking-[0.14em] text-carbon-950">Disponibilidad</legend>
                    <div class="mt-4 space-y-2.5">
                        @foreach([
                            '' => 'Todos',
                            'disponible' => 'Con existencia',
                            'bajo-pedido' => 'Bajo pedido',
                            'agotado' => 'Sin existencia',
                        ] as $value => $label)
                            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-carbon-600 transition hover:text-carbon-950">
                                <input type="radio" wire:model.live="availability" value="{{ $value }}"
                                       class="size-4 border-carbon-300 accent-brand-600 focus:ring-2 focus:ring-brand-500/40">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                {{-- Marcas --}}
                @if($this->availableBrands()->isNotEmpty())
                    <fieldset>
                        <legend class="font-display text-sm font-bold uppercase tracking-[0.14em] text-carbon-950">Marca</legend>
                        <div class="mt-4 max-h-72 space-y-2.5 overflow-y-auto pr-1">
                            @foreach($this->availableBrands() as $brand)
                                <label class="flex cursor-pointer items-center gap-2.5 text-sm text-carbon-600 transition hover:text-carbon-950">
                                    <input type="checkbox" wire:model.live="brandSlugs" value="{{ $brand->slug }}"
                                           class="size-4 rounded border-carbon-300 accent-brand-600 focus:ring-2 focus:ring-brand-500/40">
                                    <span class="flex-1">{{ $brand->name }}</span>
                                    <span class="text-xs text-carbon-400">{{ $brand->products_count }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                @if($this->hasFilters())
                    <button type="button" wire:click="clearFilters"
                            class="text-sm font-semibold text-brand-700 underline transition hover:text-brand-800">
                        Limpiar todos los filtros
                    </button>
                @endif
            </div>
        </div>
    </aside>

    {{-- Resultados --}}
    <div>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-carbon-400"
                     viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <circle cx="9" cy="9" r="6"/><path d="m13.5 13.5 3.5 3.5" stroke-linecap="round"/>
                </svg>
                <input type="search" wire:model.live.debounce.350ms="search"
                       placeholder="Buscar por nombre, SKU o marca…" aria-label="Buscar en el catálogo"
                       class="h-12 w-full rounded-full border border-carbon-300 bg-white pl-11 pr-4 text-sm text-carbon-900 outline-none transition placeholder:text-carbon-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
            </div>

            <select wire:model.live="sort" aria-label="Ordenar resultados"
                    class="h-12 rounded-full border border-carbon-300 bg-white px-4 text-sm font-medium text-carbon-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                <option value="relevancia">Más relevantes</option>
                <option value="nombre">Nombre (A–Z)</option>
                <option value="precio-asc">Precio: menor a mayor</option>
                <option value="precio-desc">Precio: mayor a menor</option>
                <option value="existencia">Mayor existencia</option>
            </select>
        </div>

        {{-- Filtros aplicados --}}
        @if($this->hasFilters())
            <div class="mt-5 flex flex-wrap items-center gap-2">
                @if($this->search !== '')
                    <button type="button" wire:click="$set('search', '')"
                            class="group inline-flex items-center gap-2 rounded-full bg-carbon-100 px-3 py-1.5 text-xs font-semibold text-carbon-700 transition hover:bg-carbon-200">
                        «{{ $this->search }}» <span class="text-carbon-400 group-hover:text-carbon-700">&times;</span>
                    </button>
                @endif

                @foreach($this->categorySlugs as $slug)
                    <button type="button" wire:click="removeCategory('{{ $slug }}')"
                            class="group inline-flex items-center gap-2 rounded-full bg-carbon-100 px-3 py-1.5 text-xs font-semibold text-carbon-700 transition hover:bg-carbon-200">
                        {{ \App\Models\Category::where('slug', $slug)->value('name') ?? $slug }}
                        <span class="text-carbon-400 group-hover:text-carbon-700">&times;</span>
                    </button>
                @endforeach

                @foreach($this->brandSlugs as $slug)
                    <button type="button" wire:click="removeBrand('{{ $slug }}')"
                            class="group inline-flex items-center gap-2 rounded-full bg-carbon-100 px-3 py-1.5 text-xs font-semibold text-carbon-700 transition hover:bg-carbon-200">
                        {{ \App\Models\Brand::where('slug', $slug)->value('name') ?? $slug }}
                        <span class="text-carbon-400 group-hover:text-carbon-700">&times;</span>
                    </button>
                @endforeach
            </div>
        @endif

        <p class="mt-6 text-sm text-carbon-500" aria-live="polite">
            <span wire:loading.remove wire:target="search,brandSlugs,categorySlugs,availability,sort">
                {{ $this->results()->total() }}
                {{ Str::plural('producto', $this->results()->total()) }}
                @if($this->search !== '') para «{{ $this->search }}» @endif
            </span>
            <span wire:loading wire:target="search,brandSlugs,categorySlugs,availability,sort">Buscando…</span>
        </p>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
             wire:loading.class="opacity-40"
             wire:target="search,brandSlugs,categorySlugs,availability,sort,page">
            @forelse($this->results() as $product)
                <x-cards.product :product="$product" wire:key="p-{{ $product->id }}" />
            @empty
                <div class="col-span-full rounded-2xl border border-carbon-200 bg-carbon-50 p-12 text-center">
                    <p class="font-display text-lg font-bold text-carbon-950">Sin resultados</p>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-carbon-600">
                        No encontramos productos con esos criterios. Prueba con menos filtros,
                        o pídenos el material directamente: también surtimos bajo pedido.
                    </p>
                    <div class="mt-6 flex flex-wrap justify-center gap-3">
                        <x-ui.button wire:click="clearFilters" type="button">Limpiar filtros</x-ui.button>
                        <x-ui.button href="{{ route('contacto') }}" variant="outline" :icon="false">Pedir cotización</x-ui.button>
                    </div>
                </div>
            @endforelse
        </div>

        @if($this->results()->hasPages())
            <div class="mt-12">{{ $this->results()->links() }}</div>
        @endif
    </div>
</div>
