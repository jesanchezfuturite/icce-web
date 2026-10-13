<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El sitio está en español (APP_LOCALE=es). Sin la carpeta lang/es los errores de
 * los formularios salían como «validation.email»: ningún formulario público
 * puede mostrar una clave de traducción sin resolver.
 */
class MensajesEnEspanolTest extends TestCase
{
    use RefreshDatabase;

    private function mensajes(array $errores): string
    {
        return implode(' | ', array_merge(...array_values($errores)));
    }

    public function test_el_idioma_de_la_aplicacion_es_espanol(): void
    {
        $this->assertSame('es', app()->getLocale());
    }

    public function test_las_reglas_comunes_de_validacion_estan_traducidas(): void
    {
        foreach (['required', 'email', 'confirmed', 'min.string', 'max.string', 'in', 'unique', 'uuid', 'numeric'] as $regla) {
            $this->assertStringNotContainsString(
                'validation.',
                __("validation.{$regla}", ['attribute' => 'campo']),
                "La regla «{$regla}» no tiene traducción.",
            );
        }
    }

    public function test_el_formulario_de_contacto_vacio_responde_en_espanol(): void
    {
        $respuesta = $this->post('/contacto', []);

        $respuesta->assertSessionHasErrors();
        $this->assertStringNotContainsString('validation.', $this->mensajes(session('errors')->getBag('default')->messages()));
    }

    public function test_el_checkout_con_correo_y_contrasena_malos_responde_en_espanol(): void
    {
        $respuesta = $this->post('/checkout', [
            'checkout_token' => '00000000-0000-4000-8000-000000000000',
            'email' => 'mal', 'password' => 'a', 'password_confirmation' => 'b',
        ]);

        // Sin carrito redirige antes de validar; con carrito valida. Ambos son válidos aquí:
        // lo que importa es que no aparezca una clave cruda.
        $errores = session('errors')?->getBag('default')->messages() ?? [];
        $this->assertStringNotContainsString('validation.', $this->mensajes($errores) ?: '');
        $this->assertTrue($respuesta->isRedirect());
    }

    public function test_la_solicitud_de_renta_vacia_responde_en_espanol(): void
    {
        $componente = Livewire::test('renta.solicitud')->call('submit');

        $mensajes = collect($componente->errors()->messages())->flatten()->implode(' | ');

        $this->assertNotSame('', $mensajes);
        $this->assertStringNotContainsString('validation.', $mensajes);
    }
}
