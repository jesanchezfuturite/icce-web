<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\BrandSeeder;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CmsSeeder;
use Database\Seeders\DemoOrderSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Separación de los tres perfiles: visitante, cliente registrado y personal de
 * ICCE. Un cliente nunca debe alcanzar el backoffice ni los pedidos ajenos.
 */
class PerfilesDeAccesoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            UserSeeder::class,
            BrandSeeder::class,
            CategorySeeder::class,
            CatalogSeeder::class,
            CmsSeeder::class,
            DemoOrderSeeder::class,
        ]);
    }

    private function client(): User
    {
        return User::where('email', 'registrado@icce.com')->firstOrFail();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@icce.com')->firstOrFail();
    }

    // ---------------- Visitante ----------------

    public function test_el_visitante_no_entra_al_portal(): void
    {
        $this->get('/portal')->assertRedirect('/ingresar');
    }

    public function test_el_visitante_ve_el_formulario_de_acceso(): void
    {
        $this->get('/ingresar')->assertOk()->assertSee('Ingresa a tu cuenta');
    }

    // ---------------- Cliente registrado ----------------

    public function test_el_cliente_entra_con_sus_credenciales(): void
    {
        $this->post('/ingresar', [
            'email' => 'registrado@icce.com',
            'password' => UserSeeder::REVIEW_PASSWORD,
        ])->assertRedirect('/portal');

        $this->assertAuthenticatedAs($this->client());
    }

    public function test_el_cliente_ve_solo_sus_pedidos(): void
    {
        $this->actingAs($this->client())
            ->get('/portal')
            ->assertOk()
            ->assertSee('VD-2026-00001');
    }

    public function test_el_cliente_no_ve_el_pedido_de_otro(): void
    {
        $otro = User::factory()->create(['role' => UserRole::Client]);
        $order = Order::firstOrFail();
        $order->update(['user_id' => $otro->id]);

        $this->actingAs($this->client())
            ->get("/portal/pedido/{$order->folio}")
            ->assertNotFound();
    }

    public function test_el_cliente_no_entra_al_backoffice(): void
    {
        $this->actingAs($this->client())->get('/admin')->assertForbidden();
    }

    public function test_una_cuenta_desactivada_no_puede_entrar(): void
    {
        $this->client()->update(['is_active' => false]);

        $this->post('/ingresar', [
            'email' => 'registrado@icce.com',
            'password' => UserSeeder::REVIEW_PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // ---------------- Administrador ----------------

    public function test_el_administrador_aterriza_en_el_backoffice(): void
    {
        $this->post('/ingresar', [
            'email' => 'admin@icce.com',
            'password' => UserSeeder::REVIEW_PASSWORD,
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($this->admin());
    }

    public function test_el_administrador_abre_el_backoffice(): void
    {
        $this->actingAs($this->admin())->get('/admin')->assertOk();
    }

    // ---------------- Credenciales ----------------

    public function test_una_contrasena_incorrecta_no_abre_sesion(): void
    {
        $this->post('/ingresar', [
            'email' => 'registrado@icce.com',
            'password' => 'incorrecta',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_cerrar_sesion_devuelve_al_inicio(): void
    {
        $this->actingAs($this->client())
            ->post('/salir')
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
