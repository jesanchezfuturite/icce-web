<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Registro abierto desde el login: crea siempre una cuenta de cliente.
 */
class RegistroDeClienteTest extends TestCase
{
    use RefreshDatabase;

    private function datos(array $extra = []): array
    {
        return array_merge([
            'name' => 'Arq. Laura Prueba', 'email' => 'laura@obra.mx', 'phone' => '8112345678',
            'company' => 'Obras LP', 'password' => 'Secreto2026', 'password_confirmation' => 'Secreto2026',
        ], $extra);
    }

    public function test_el_login_invita_a_crear_una_cuenta_y_enlaza_al_registro(): void
    {
        $this->get('/ingresar')
            ->assertOk()
            ->assertSee('¿Aún no tienes cuenta?')
            ->assertSee('Crea una aquí')
            ->assertSee('y disfruta de nuestro catálogo')
            ->assertSee(route('register'), false);
    }

    public function test_la_pagina_de_registro_carga_y_enlaza_de_vuelta_al_login(): void
    {
        $this->get('/registro')
            ->assertOk()
            ->assertSee('Crea tu cuenta')
            ->assertSee(route('login'), false);
    }

    public function test_registrarse_crea_una_cuenta_de_cliente_e_inicia_sesion(): void
    {
        $this->post('/registro', $this->datos())->assertRedirect(route('catalogo.index'));

        $usuario = User::where('email', 'laura@obra.mx')->sole();

        $this->assertSame(UserRole::Client, $usuario->role);
        $this->assertTrue($usuario->is_active);
        $this->assertSame('Obras LP', $usuario->company);
        $this->assertTrue(Hash::check('Secreto2026', $usuario->password));
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_el_telefono_y_la_empresa_son_opcionales(): void
    {
        $this->post('/registro', $this->datos(['phone' => null, 'company' => null]))->assertRedirect();

        $this->assertSame(1, User::count());
    }

    public function test_no_se_puede_registrar_como_administrador_aunque_se_fuerce_el_rol(): void
    {
        $this->post('/registro', $this->datos(['role' => 'admin', 'is_active' => false]))->assertRedirect();

        $usuario = User::sole();
        $this->assertSame(UserRole::Client, $usuario->role);
        $this->assertTrue($usuario->is_active);
    }

    public function test_el_correo_se_guarda_en_minusculas_y_no_permite_duplicados(): void
    {
        $this->post('/registro', $this->datos(['email' => ' Laura@Obra.MX ']))->assertRedirect();
        $this->assertSame('laura@obra.mx', User::sole()->email);

        auth()->logout();

        $this->post('/registro', $this->datos(['email' => 'LAURA@obra.mx']))->assertSessionHasErrors('email');
        $this->assertSame(1, User::count());
    }

    public function test_datos_invalidos_no_crean_cuenta_y_responden_en_espanol(): void
    {
        $this->post('/registro', [
            'name' => '', 'email' => 'mal', 'password' => 'corta', 'password_confirmation' => 'otra',
        ])->assertSessionHasErrors(['name', 'email', 'password']);

        $this->assertSame(0, User::count());
        $this->assertGuest();
        $this->assertStringNotContainsString(
            'validation.',
            implode(' ', array_merge(...array_values(session('errors')->getBag('default')->messages()))),
        );
    }

    public function test_quien_ya_inicio_sesion_no_ve_el_registro(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Client]))
            ->get('/registro')
            ->assertRedirect();
    }

    public function test_despues_de_registrarse_la_cuenta_puede_entrar_al_portal(): void
    {
        $this->post('/registro', $this->datos());

        $this->get('/portal')->assertOk();
    }
}
