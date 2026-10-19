<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El backoffice es sólo para el personal. Como el checkout y el registro dejan
 * al cliente con sesión iniciada, un cliente que escribe /admin ve un 403: la
 * pantalla debe explicar por qué y ofrecer cerrar sesión, no quedarse muda.
 */
class AccesoRestringidoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_un_cliente_no_entra_al_backoffice_y_ve_como_salir(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client, 'is_active' => true, 'email' => 'cliente@obra.mx']);

        $this->actingAs($cliente)
            ->get('/admin')
            ->assertForbidden()
            ->assertSee('No tienes permiso para ver esta página')
            ->assertSee('cliente@obra.mx')
            ->assertSee('Cerrar sesión')
            ->assertSee(route('logout'), false);
    }

    public function test_un_visitante_sin_sesion_va_al_login_del_backoffice_no_a_un_403(): void
    {
        $this->get('/admin')->assertRedirect();
    }

    public function test_una_cuenta_de_personal_desactivada_recibe_403(): void
    {
        $agente = User::factory()->create(['role' => UserRole::Sales, 'is_active' => false]);

        $this->actingAs($agente)->get('/admin')->assertForbidden();
    }

    public function test_el_personal_activo_si_entra(): void
    {
        foreach ([UserRole::Admin, UserRole::Sales] as $rol) {
            $this->actingAs(User::factory()->create(['role' => $rol, 'is_active' => true]))
                ->get('/admin')
                ->assertOk();
        }
    }

    public function test_cerrar_sesion_desde_el_403_devuelve_al_visitante_al_login_del_admin(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client, 'is_active' => true]);

        $this->actingAs($cliente)->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();

        $this->get('/admin')->assertRedirect();
    }
}
