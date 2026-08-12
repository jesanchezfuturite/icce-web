<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * En el sector de la construcción la gente se presenta con su título —«Ing.
 * Rodrigo Cantú», «Arq. Mariana Treviño»—, así que tomar la primera palabra
 * para saludar produce «Hola, Ing.». Esto salta los tratamientos y devuelve
 * el nombre de pila.
 */
final class PersonName
{
    private const TITLES = [
        'ing', 'inge', 'arq', 'lic', 'sr', 'sra', 'srta', 'dr', 'dra',
        'mtro', 'mtra', 'cp', 'c.p', 'tec', 'téc', 'don', 'doña',
    ];

    public static function first(?string $fullName): string
    {
        $words = preg_split('/\s+/u', trim((string) $fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($words as $word) {
            $clean = Str::lower(rtrim($word, '.'));

            if (in_array($clean, self::TITLES, true)) {
                continue;
            }

            return $word;
        }

        // Sólo venía el tratamiento, o el campo llegó vacío
        return $words === [] ? 'de nuevo' : $words[0];
    }
}
