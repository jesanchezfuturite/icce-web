<?php

namespace App\Support;

use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

/**
 * Guarda en el disco `site` una imagen de producto subida desde el backoffice.
 *
 * Con GD disponible la deja en WebP de 400 KB o menos (ProductImageOptimizer).
 * Sin GD guarda el archivo tal cual: en ese caso el formulario ya limitó su peso.
 * Siempre en la carpeta de subidas, que es la única de la que el sistema se
 * permite borrar archivos (las imágenes originales del catálogo no se tocan).
 */
final class ProductImageStorage
{
    public static function store(TemporaryUploadedFile|UploadedFile $file, ?string $baseName = null): string
    {
        $disk = Storage::disk(ProductImage::DISK);
        $name = Str::slug($baseName ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'producto';
        $unique = $name.'-'.Str::lower(Str::random(6));
        $directory = ProductImage::UPLOAD_DIRECTORY;

        if (! ProductImageOptimizer::isAvailable()) {
            $path = $directory.'/'.$unique.'.'.($file->guessExtension() ?: $file->getClientOriginalExtension());
            $disk->put($path, (string) file_get_contents($file->getRealPath()), 'public');

            return $path;
        }

        try {
            $bytes = ProductImageOptimizer::optimize(
                (string) file_get_contents($file->getRealPath()),
                orientation: ProductImageOptimizer::orientationOf($file->getRealPath()),
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            throw ValidationException::withMessages(['data.images' => $e->getMessage()]);
        }

        $path = $directory.'/'.$unique.'.webp';
        $disk->put($path, $bytes, 'public');

        return $path;
    }
}
