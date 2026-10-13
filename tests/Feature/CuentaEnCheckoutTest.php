<?php

namespace Tests\Feature;

use App\Actions\Checkout\OutOfStockException;
use App\Actions\Checkout\PlaceOrders;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Cart\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Comprar o cotizar exige cuenta, pero se pide hasta el último paso: el
 * visitante arma el carrito libremente y crea su cuenta justo antes de pagar.
 */
class CuentaEnCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Product $producto;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $categoria = Category::create(['name' => 'Llanas', 'slug' => 'llanas']);
        $this->producto = Product::create([
            'name' => 'Llana de acabado', 'slug' => 'llana', 'sku' => 'LL-001',
            'category_id' => $categoria->id, 'price' => 1000, 'stock_qty' => 50,
            'max_direct_purchase' => 10, 'is_active' => true, 'is_for_sale' => true,
        ]);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'checkout_token' => (string) \Illuminate\Support\Str::uuid(),
            'nombre' => 'Ing. Rodrigo Cantú', 'email' => 'rodrigo@obra.mx', 'telefono' => '8112345678',
            'empresa' => 'Obras del Norte', 'rfc' => 'ONO010101AB1',
            'calle' => 'Av. Barragán 1234', 'ciudad' => 'Monterrey', 'estado' => 'Nuevo León', 'cp' => '64000',
            'metodo_pago' => 'card', 'tarjeta' => '4242424242424242',
            'password' => 'Secreto2026', 'password_confirmation' => 'Secreto2026',
        ], $extra);
    }

    public function test_el_carrito_se_arma_sin_cuenta(): void
    {
        $this->get('/carrito')->assertOk();

        app(Cart::class)->add($this->producto, 2);

        $this->get('/checkout')->assertOk();
    }

    public function test_el_checkout_pide_crear_la_cuenta_a_un_visitante(): void
    {
        app(Cart::class)->add($this->producto, 2);

        $this->get('/checkout')
            ->assertOk()
            ->assertSee('Crea tu cuenta')
            ->assertSee('name="password"', false)
            ->assertSee('Inicia sesión');
    }

    public function test_quien_ya_inicio_sesion_no_ve_el_bloque_de_cuenta(): void
    {
        app(Cart::class)->add($this->producto, 2);

        $this->actingAs(User::factory()->create(['role' => UserRole::Client]))->get('/checkout')
            ->assertOk()
            ->assertDontSee('Crea tu cuenta');
    }

    public function test_comprar_crea_la_cuenta_inicia_sesion_y_liga_la_orden(): void
    {
        app(Cart::class)->add($this->producto, 2);

        $this->post('/checkout', $this->datos())->assertRedirect('/checkout/gracias');

        $usuario = User::where('email', 'rodrigo@obra.mx')->sole();

        $this->assertSame(UserRole::Client, $usuario->role);
        $this->assertSame('Obras del Norte', $usuario->company);
        $this->assertSame('ONO010101AB1', $usuario->rfc);
        $this->assertTrue(Hash::check('Secreto2026', $usuario->password));
        $this->assertNotSame('Secreto2026', $usuario->password);

        $this->assertAuthenticatedAs($usuario);
        $this->assertSame($usuario->id, Order::sole()->user_id);
    }

    public function test_el_cliente_recien_registrado_ve_su_pedido_en_el_portal(): void
    {
        app(Cart::class)->add($this->producto, 2);
        $this->post('/checkout', $this->datos());

        $orden = Order::sole();

        $this->get(route('portal.pedido', $orden))->assertOk()->assertSee($orden->folio);
    }

    public function test_una_cotizacion_tambien_exige_cuenta(): void
    {
        app(Cart::class)->add($this->producto, 40);   // rebasa el máximo en línea → cotización

        $this->post('/checkout', $this->datos(['password' => null, 'password_confirmation' => null, 'metodo_pago' => null, 'tarjeta' => null]))
            ->assertSessionHasErrors('password');

        $this->assertSame(0, Order::count());

        $this->post('/checkout', $this->datos(['metodo_pago' => null, 'tarjeta' => null]))
            ->assertRedirect('/checkout/gracias');

        $this->assertSame(OrderStatus::Pending, Order::sole()->status);
        $this->assertNotNull(Order::sole()->user_id);
    }

    public function test_sin_contrasena_no_se_crea_la_orden_ni_la_cuenta(): void
    {
        app(Cart::class)->add($this->producto, 2);

        $this->post('/checkout', $this->datos(['password' => '', 'password_confirmation' => '']))
            ->assertSessionHasErrors('password');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, User::count());
    }

    public function test_la_contrasena_debe_confirmarse_y_tener_ocho_caracteres(): void
    {
        app(Cart::class)->add($this->producto, 2);

        $this->post('/checkout', $this->datos(['password_confirmation' => 'otra-cosa']))
            ->assertSessionHasErrors('password');

        $this->post('/checkout', $this->datos(['password' => 'corta', 'password_confirmation' => 'corta']))
            ->assertSessionHasErrors('password');

        $this->assertSame(0, User::count());
    }

    public function test_un_correo_ya_registrado_pide_iniciar_sesion_en_vez_de_duplicar_la_cuenta(): void
    {
        User::factory()->create(['email' => 'rodrigo@obra.mx']);
        app(Cart::class)->add($this->producto, 2);

        $this->post('/checkout', $this->datos())->assertSessionHasErrors('email');

        $this->assertSame(1, User::count());
        $this->assertSame(0, Order::count());
    }

    public function test_si_falla_el_pedido_no_queda_una_cuenta_a_medias(): void
    {
        $this->mock(PlaceOrders::class)
            ->shouldReceive('__invoke')
            ->andThrow(new OutOfStockException(['Llana de acabado']));

        app(Cart::class)->add($this->producto, 2);

        $this->post('/checkout', $this->datos())->assertSessionHasErrors('carrito');

        $this->assertSame(0, User::count());
        $this->assertGuest();
    }

    public function test_el_inicio_de_sesion_desde_el_checkout_regresa_al_checkout(): void
    {
        User::factory()->create(['email' => 'rodrigo@obra.mx', 'password' => 'Secreto2026']);
        app(Cart::class)->add($this->producto, 2);

        $this->get('/ingresar?volver=checkout')->assertOk();

        $this->post('/ingresar', ['email' => 'rodrigo@obra.mx', 'password' => 'Secreto2026'])
            ->assertRedirect('/checkout');

        // El carrito sobrevive al inicio de sesión
        $this->assertFalse(app(Cart::class)->isEmpty());
    }

    public function test_el_registro_no_filtra_la_contrasena_al_formulario(): void
    {
        app(Cart::class)->add($this->producto, 2);

        $this->from('/checkout')->post('/checkout', $this->datos(['calle' => '']))
            ->assertSessionHasErrors('calle')
            ->assertSessionMissing('_old_input.password');
    }
}
