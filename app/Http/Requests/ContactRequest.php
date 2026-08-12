<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ContactRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'empresa' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:191'],
            'telefono' => ['required', 'string', 'max:50'],
            'obra' => ['nullable', 'string', 'max:150'],
            'asunto' => ['required', 'string', 'max:80'],
            'mensaje' => ['required', 'string', 'min:10', 'max:2000'],
            'acepto' => ['accepted'],
            // Trampa para robots: un campo que un humano nunca ve ni llena.
            'apellido_materno' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'email' => 'correo electrónico',
            'telefono' => 'teléfono',
            'asunto' => 'asunto',
            'mensaje' => 'mensaje',
            'acepto' => 'aviso de privacidad',
        ];
    }

    public function messages(): array
    {
        return [
            'mensaje.min' => 'Cuéntanos un poco más para poder ayudarte bien.',
            'acepto.accepted' => 'Necesitamos tu consentimiento para poder contactarte.',
            'apellido_materno.prohibited' => 'No pudimos procesar el envío.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                // Un mensaje lleno de enlaces casi siempre es spam
                if (substr_count(mb_strtolower((string) $this->input('mensaje')), 'http') > 2) {
                    $validator->errors()->add('mensaje', 'No pudimos procesar el envío.');
                }
            },
        ];
    }
}
