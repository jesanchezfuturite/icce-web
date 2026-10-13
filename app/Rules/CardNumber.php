<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Número de tarjeta: de 13 a 19 dígitos, con espacios o guiones de separación.
 *
 * Es una validación de forma para no mandar basura a la pasarela, no una
 * verificación del banco. Con una pasarela real el número no debe pasar por
 * este servidor: se tokeniza en el navegador y aquí llegaría sólo el token.
 */
class CardNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/[\s-]+/', '', (string) $value);

        if (! ctype_digit($digits) || strlen($digits) < 13 || strlen($digits) > 19) {
            $fail('El número de tarjeta no es válido.');
        }
    }
}
