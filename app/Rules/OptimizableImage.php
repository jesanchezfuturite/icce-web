<?php

namespace App\Rules;

use App\Support\ProductImageOptimizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Revisa lo barato antes de procesar una imagen: que sea una imagen real y que
 * sus dimensiones no obliguen a GD a gastar cientos de MB de memoria.
 */
class OptimizableImage implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $path = is_object($value) && method_exists($value, 'getRealPath') ? $value->getRealPath() : null;

        if (! $path) {
            return;
        }

        $info = @getimagesize($path);

        if ($info === false) {
            $fail('El archivo no es una imagen válida.');

            return;
        }

        if ($info[0] * $info[1] > ProductImageOptimizer::MAX_PIXELS) {
            $fail('La imagen es demasiado grande en dimensiones (máximo 40 megapíxeles).');
        }
    }
}
