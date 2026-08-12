<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'logo_path', 'website', 'description', 'is_featured', 'sort_order'])]
class Brand extends Model
{
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Marcas del carrusel del home (1.4), en el orden definido por el CMS. */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true)->orderBy('sort_order');
    }
}
