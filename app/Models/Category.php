<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'parent_id', 'name', 'slug', 'description', 'image_path',
    'is_active', 'sort_order', 'meta_title', 'meta_description',
])]
class Category extends Model
{
    /** Niveles permitidos: principal › subcategoría › sub-subcategoría › cuarto nivel. */
    public const MAX_DEPTH = 4;

    /** Clave del menú del catálogo que cachea AppServiceProvider. */
    public const NAV_CACHE_KEY = 'nav.categories.v2';

    protected static function booted(): void
    {
        // El menú se cachea una hora: sin esto una categoría nueva o editada
        // tardaría hasta ese tiempo en verse.
        static::saved(fn () => Cache::forget(self::NAV_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::NAV_CACHE_KEY));
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * IDs de esta categoría y toda su descendencia, para filtrar el catálogo.
     * Consulta explícita cuando la relación no viene precargada, para no chocar
     * con preventLazyLoading.
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];

        $children = $this->relationLoaded('children')
            ? $this->children
            : $this->children()->get();

        foreach ($children as $child) {
            $ids = array_merge($ids, $child->descendantIds());
        }

        return $ids;
    }

    /**
     * Ancestros de esta categoría, de la principal hacia abajo. Consultas
     * explícitas, no la relación `parent`, para no chocar con preventLazyLoading;
     * el tope evita un bucle si los datos tuvieran un ciclo.
     *
     * @return Collection<int, Category>
     */
    public function ancestors(): Collection
    {
        $chain = new Collection;
        $parentId = $this->parent_id;

        while ($parentId !== null && $chain->count() < self::MAX_DEPTH) {
            $parent = static::query()->find($parentId);

            if ($parent === null) {
                break;
            }

            $chain->prepend($parent);
            $parentId = $parent->parent_id;
        }

        return $chain;
    }

    /** Niveles que cuelgan debajo de esta categoría (0 si no tiene hijas). */
    public function height(): int
    {
        $children = $this->relationLoaded('children')
            ? $this->children
            : $this->children()->get();

        return $children->isEmpty()
            ? 0
            : 1 + $children->max(fn (Category $child) => $child->height());
    }

    /**
     * Categorías que pueden ser padre de `$record` sin pasar de MAX_DEPTH niveles
     * ni crear un ciclo. Con `$record` nulo, las posibles para una categoría nueva.
     *
     * @return array<int, string> id => ruta completa («Materiales › Selladores»)
     */
    public static function parentOptionsFor(?self $record = null): array
    {
        $paths = static::pathsById();

        $excluded = $record?->exists ? $record->descendantIds() : [];
        $height = $record?->exists ? $record->height() : 0;

        $options = collect($paths)
            ->reject(fn (array $names, int $id) => in_array($id, $excluded, true))
            ->filter(fn (array $names) => count($names) + 1 + $height <= self::MAX_DEPTH)
            ->map(fn (array $names) => implode(' › ', $names))
            ->all();

        // Orden alfabético por ruta: las hijas quedan agrupadas bajo su padre.
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    /**
     * Ruta de cada categoría desde la principal hasta ella, en una sola consulta.
     *
     * @return array<int, array<int, string>> id => nombres
     */
    public static function pathsById(): array
    {
        $all = static::query()->get(['id', 'name', 'parent_id'])->keyBy('id');
        $paths = [];

        foreach ($all as $category) {
            $names = [];
            $id = $category->id;

            for ($i = 0; $id !== null && isset($all[$id]) && $i < self::MAX_DEPTH; $i++) {
                array_unshift($names, $all[$id]->name);
                $id = $all[$id]->parent_id;
            }

            $paths[$category->id] = $names;
        }

        return $paths;
    }

    /** Productos activos en esta categoría y su descendencia. */
    public function totalProducts(): int
    {
        return Product::query()
            ->active()
            ->whereIn('category_id', $this->descendantIds())
            ->count();
    }
}
