<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['product_id', 'path', 'alt', 'is_primary', 'sort_order'])]
class ProductImage extends Model
{
    /** Disco donde viven las imágenes del sitio (public/). */
    public const DISK = 'site';

    /**
     * Carpeta de las imágenes subidas desde el backoffice. Es la única de la que
     * el sistema borra archivos: las imágenes originales del catálogo, que otros
     * productos pueden compartir, nunca se tocan.
     */
    public const UPLOAD_DIRECTORY = 'images/productos/subidas';

    protected static function booted(): void
    {
        static::saved(function (self $image) {
            // Sólo una principal por producto: marcar una desmarca las demás
            if ($image->is_primary) {
                static::query()
                    ->where('product_id', $image->product_id)
                    ->whereKeyNot($image->getKey())
                    ->update(['is_primary' => false]);
            }

            // Si cambió el archivo, el anterior queda huérfano
            if ($image->wasChanged('path')) {
                self::forgetFile($image->getOriginal('path'));
            }
        });

        static::deleted(function (self $image) {
            self::forgetFile($image->path);

            // Si se borra la principal, la siguiente la releva
            Product::find($image->product_id)?->ensurePrimaryImage();
        });
    }

    /** Borra el archivo sólo si es una subida y ninguna otra imagen lo usa. */
    private static function forgetFile(?string $path): void
    {
        if (! $path
            || ! str_starts_with($path, self::UPLOAD_DIRECTORY.'/')
            || str_contains($path, '..')
            || static::query()->where('path', $path)->exists()) {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
