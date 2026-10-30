<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

/**
 * Deja una imagen de producto en WebP y por debajo del peso máximo (400 KB).
 *
 * El catálogo carga decenas de imágenes por pantalla: una foto de celular de
 * 5 MB lo vuelve lento. En vez de rechazarla, se reduce al subirla: se corrige
 * la orientación, se limita el lado mayor a 1600 px, se convierte a WebP y se
 * baja la calidad (y, si hace falta, el tamaño) hasta que cabe. Una imagen que
 * ya es WebP y ya cumple se guarda tal cual, sin recomprimirla.
 *
 * Usa GD. Si el servidor no trae GD con WebP, `isAvailable()` es falso y el
 * formulario pasa a exigir archivos de 400 KB o menos sin tocarlos.
 */
final class ProductImageOptimizer
{
    public const MAX_BYTES = 400 * 1024;

    public const MAX_SIDE = 1600;

    /** Por debajo de este lado ya no se reduce más: se prefiere avisar. */
    public const MIN_SIDE = 600;

    /** Tope de píxeles a decodificar: GD gasta ~4 bytes por píxel en memoria. */
    public const MAX_PIXELS = 40_000_000;

    private const QUALITIES = [85, 80, 75, 70, 60, 50, 40];

    public static function isAvailable(): bool
    {
        return function_exists('imagewebp')
            && function_exists('imagecreatefromstring')
            && function_exists('imagecreatetruecolor');
    }

    /**
     * @param  int|null  $orientation  Valor EXIF de orientación (1 a 8), si se conoce
     * @return string Bytes de la imagen WebP resultante
     *
     * @throws InvalidArgumentException si los bytes no son una imagen válida o es enorme
     * @throws RuntimeException si ni reduciéndola al mínimo cabe en el peso máximo
     */
    public static function optimize(string $bytes, int $maxBytes = self::MAX_BYTES, ?int $orientation = null): string
    {
        $info = @getimagesizefromstring($bytes);

        if ($info === false) {
            throw new InvalidArgumentException('El archivo no es una imagen válida.');
        }

        [$width, $height] = $info;

        if ($width * $height > self::MAX_PIXELS) {
            throw new InvalidArgumentException('La imagen es demasiado grande en dimensiones (máximo 40 megapíxeles).');
        }

        // Ya es WebP, ya pesa poco y ya es de tamaño razonable: no se recomprime
        if (($info[2] ?? null) === IMAGETYPE_WEBP
            && strlen($bytes) <= $maxBytes
            && max($width, $height) <= self::MAX_SIDE
            && in_array($orientation, [null, 1], true)) {
            return $bytes;
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            throw new InvalidArgumentException('No se pudo leer la imagen. Prueba con otro archivo.');
        }

        $image = self::orient($image, $orientation);
        $image = self::asTrueColor($image);

        $side = min(max(imagesx($image), imagesy($image)), self::MAX_SIDE);

        while (true) {
            $candidate = self::fitTo($image, $side);

            foreach (self::QUALITIES as $quality) {
                $encoded = self::encode($candidate, $quality);

                if (strlen($encoded) <= $maxBytes) {
                    return $encoded;
                }
            }

            $side = (int) floor($side * 0.85);

            if ($side < self::MIN_SIDE) {
                throw new RuntimeException('No se pudo reducir la imagen a '.(int) ($maxBytes / 1024).' KB sin dejarla demasiado chica. Usa una imagen más simple o más pequeña.');
            }
        }
    }

    /** Lee la orientación EXIF de un archivo JPEG (las fotos de celular la traen). */
    public static function orientationOf(string $path): ?int
    {
        if (! function_exists('exif_read_data')) {
            return null;
        }

        $exif = @exif_read_data($path);
        $value = is_array($exif) ? ($exif['Orientation'] ?? null) : null;

        return is_numeric($value) ? (int) $value : null;
    }

    private static function orient(\GdImage $image, ?int $orientation): \GdImage
    {
        $degrees = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($degrees === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $degrees, 0);

        return $rotated === false ? $image : $rotated;
    }

    /** WebP necesita color verdadero; las imágenes de paleta (PNG/GIF) se convierten. */
    private static function asTrueColor(\GdImage $image): \GdImage
    {
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    private static function fitTo(\GdImage $image, int $side): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= $side) {
            return $image;
        }

        $ratio = $side / $longest;
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $resized;
    }

    private static function encode(\GdImage $image, int $quality): string
    {
        ob_start();
        imagewebp($image, null, $quality);

        return (string) ob_get_clean();
    }
}
