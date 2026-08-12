<?php

namespace App\Models;

use App\Enums\PurchaseMode;
use App\Enums\RentalCoverage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'brand_id', 'category_id', 'sku', 'name', 'slug', 'short_description', 'description',
    'price', 'compare_at_price', 'unit', 'stock_qty', 'low_stock_threshold',
    'max_direct_purchase', 'is_on_demand', 'is_rental', 'is_for_sale', 'rental_coverage',
    'tech_sheet_pdf', 'safety_sheet_pdf', 'specs',
    'is_active', 'is_featured', 'meta_title', 'meta_description',
])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'specs' => 'array',
            'is_on_demand' => 'boolean',
            'is_rental' => 'boolean',
            'is_for_sale' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'rental_coverage' => RentalCoverage::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /** @return HasOne<ProductImage, $this> */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order');
    }

    // -----------------------------------------------------------------
    // Motor de decisión Comprar vs. Cotizar (REQ-01 / REQ-02)
    // -----------------------------------------------------------------

    /**
     * Decide cómo debe tratarse una cantidad de este producto.
     *
     * Se cobra en línea solo si el producto es de venta, no está marcado como
     * "bajo pedido", hay existencia suficiente y la cantidad no rebasa el
     * límite parametrizado. Cualquier otro caso cae en cotización.
     */
    public function purchaseModeFor(int $quantity): PurchaseMode
    {
        if (! $this->is_for_sale || $this->is_on_demand) {
            return PurchaseMode::Quote;
        }

        if ($quantity < 1 || $quantity > $this->max_direct_purchase) {
            return PurchaseMode::Quote;
        }

        if ($quantity > $this->stock_qty) {
            return PurchaseMode::Quote;
        }

        return PurchaseMode::Buy;
    }

    public function isDirectlyPurchasable(): bool
    {
        return $this->purchaseModeFor(1) === PurchaseMode::Buy;
    }

    /** Etiqueta de existencia mostrada en la ficha (3.3). */
    public function stockLabel(): string
    {
        return match (true) {
            $this->is_on_demand => 'Bajo pedido',
            $this->stock_qty <= 0 => 'Sin existencia',
            $this->stock_qty <= $this->low_stock_threshold => 'Últimas piezas',
            default => 'Disponible',
        };
    }

    public function isLowStock(): bool
    {
        return ! $this->is_on_demand
            && $this->stock_qty > 0
            && $this->stock_qty <= $this->low_stock_threshold;
    }

    // -----------------------------------------------------------------
    // Scopes de catálogo
    // -----------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForSale(Builder $query): Builder
    {
        return $query->where('is_for_sale', true);
    }

    public function scopeRentals(Builder $query): Builder
    {
        return $query->where('is_rental', true);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('is_on_demand', false)
            ->whereColumn('stock_qty', '<=', 'low_stock_threshold');
    }

    /**
     * Búsqueda por texto sobre nombre, SKU, descripción y marca.
     *
     * Cada palabra debe aparecer en algún campo (Y de O), de modo que
     * "llana kraft" no devuelva todas las llanas ni todo lo de Kraft Tool.
     *
     * Es una búsqueda con LIKE: correcta y suficiente para el orden de
     * magnitud del catálogo actual. Cuando la carga del ERP lo lleve a decenas
     * de miles de SKU, el reemplazo natural es un índice FULLTEXT o Scout.
     */
    public function scopeSearch(Builder $query, ?string $terms): Builder
    {
        $terms = trim((string) $terms);

        if ($terms === '') {
            return $query;
        }

        foreach (preg_split('/\s+/u', $terms) as $term) {
            $like = '%'.addcslashes($term, '%_\\').'%';

            $query->where(function (Builder $q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('short_description', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('brand', fn (Builder $b) => $b->where('name', 'like', $like));
            });
        }

        return $query;
    }

    /** Ordena por qué tan al principio del nombre aparece el término buscado. */
    public function scopeOrderByRelevance(Builder $query, ?string $terms): Builder
    {
        $terms = trim((string) $terms);

        if ($terms === '') {
            return $query->orderByDesc('is_featured')->orderBy('name');
        }

        $prefix = addcslashes($terms, '%_\\').'%';
        $anywhere = '%'.addcslashes($terms, '%_\\').'%';

        return $query
            ->orderByRaw(
                'CASE WHEN sku = ? THEN 0 WHEN name LIKE ? THEN 1 WHEN name LIKE ? THEN 2 ELSE 3 END',
                [$terms, $prefix, $anywhere],
            )
            ->orderBy('name');
    }

    /** Productos que hoy se pueden cobrar en línea (REQ-01). */
    public function scopeDirectlyPurchasable(Builder $query): Builder
    {
        return $query->where('is_for_sale', true)
            ->where('is_on_demand', false)
            ->where('stock_qty', '>', 0);
    }
}
