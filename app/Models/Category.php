<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'parent_id', 'name', 'slug', 'description', 'image_path',
    'is_active', 'sort_order', 'meta_title', 'meta_description',
])]
class Category extends Model
{
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

    /** Productos activos en esta categoría y su descendencia. */
    public function totalProducts(): int
    {
        return Product::query()
            ->active()
            ->whereIn('category_id', $this->descendantIds())
            ->count();
    }
}
