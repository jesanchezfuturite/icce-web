<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'title', 'slug', 'client', 'location', 'year', 'area_m2',
    'summary', 'body', 'cover_image', 'services', 'is_featured', 'sort_order',
])]
class Project extends Model
{
    protected function casts(): array
    {
        return [
            'services' => 'array',
            'is_featured' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true)->orderBy('sort_order');
    }
}
