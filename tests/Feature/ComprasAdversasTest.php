<?php

namespace Tests\Feature;

use App\Actions\Checkout\CheckoutData;
use App\Actions\Checkout\OutOfStockException;
use App\Actions\Checkout\PlaceOrders;
use App\Actions\Orders\ChangeOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PurchaseMode;
use App\Enums\UserRole;
use App\Mail\OrderPlacedMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Cart\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Revisión adversa del flujo de compra: campos malos, errores forzados,
 * compras repetidas y cobros dobles. Cada prueba fija una regla que, si se
 * rompe, cuesta dinero o deja pedidos mal registrados.
 */
class ComprasAdversasTest extends TestCase
{
    use RefreshDatabase;

    private Product $producto;

    private Category $categoria;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->categoria = Category::create(['name' => 'Llanas', 'slug' => 'llanas']);
        $this->producto = $this->crearProducto('LL-001', 1000, stock: 50);
    }

    private function crearProducto(string $sku, float $precio, int $stock = 10, array $extra = []): Product
    {
        return Product::create(array_merge([
            'name' => "Producto {$sku}", 'slug' => Str::slug("producto-{$sku}"), 'sku' => $sku,
            'category_id' => $this->categoria->id, 'price' => $precio, 'stock_qty' => $stock,
            'max_direct_purchase' => 10, 'is_active' => true, 'is_for_sale' => true,
        ], $extra));
    }

    private function cart(): Cart
    {
        return app(Cart::class);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'checkout_token' => (string) Str::uuid(),
            'nombre' => 'Ing. Rodrigo Cantú', 'email' => 'rodrigo@obra.mx', 'telefono' => '8112345678',
            'calle' => 'Av. Barragán 1234', 'ciudad' => 'Monterrey', 'estado' => 'Nuevo León', 'cp' => '64000',
            'metodo_pago' => 'card', 'tarjeta' => '4242424242424242',
            'password' => 'Secreto2026', 'password_confirmation' => 'Secreto2026',
        ], $extra);
    }

    private function comprador(): User
    {
        return User::factory()->create(['role' => UserRole::Client, 'is_active' => true]);
    }

    // ---------------- Campos malos ----------------

    #[DataProvider('camposInvalidos')]
    public function test_el_checkout_rechaza_campos_invalidos_sin_crear_nada(array $malos, string $campo): void
    {
        $this->cart()->add($this->producto, 2);

        $this->post('/checkout', $this->datos($malos))->assertSessionHasErrors($campo);

        $this->assertSame(0, Order::count());
        $this->assertSame(0, User::count());
        $this->assertSame(50, $this->producto->fresh()->stock_qty);
    }

    public static function camposInvalidos(): array
    {
        return [
            'correo sin arroba' => [['email' => 'no-es-correo'], 'email'],
            'correo sin dominio' => [['email' => 'a@'], 'email'],
            'correo con espacios' => [['email' => 'a b@obra.mx'], 'email'],
            'correo larguísimo' => [['email' => str_repeat('a', 200).'@obra.mx'], 'email'],
            'nombre vacío' => [['nombre' => ''], 'nombre'],
            'nombre de 151 caracteres' => [['nombre' => str_repeat('N', 151)], 'nombre'],
            'teléfono vacío' => [['telefono' => ''], 'telefono'],
            'cp de 11 caracteres' => [['cp' => '12345678901'], 'cp'],
            'calle vacía' => [['calle' => ''], 'calle'],
            'notas de 1001 caracteres' => [['notas' => str_repeat('x', 1001)], 'notas'],
            'método de pago inventado' => [['metodo_pago' => 'bitcoin'], 'metodo_pago'],
            'sin método de pago' => [['metodo_pago' => null], 'metodo_pago'],
            'tarjeta vacía' => [['tarjeta' => ''], 'tarjeta'],
            'tarjeta con letras' => [['tarjeta' => 'abcd efgh ijkl mnop'], 'tarjeta'],
            'tarjeta demasiado corta' => [['tarjeta' => '1234'], 'tarjeta'],
            'tarjeta de 26 caracteres' => [['tarjeta' => str_repeat('4', 26)], 'tarjeta'],
            'contraseña corta' => [['password' => 'abc', 'password_confirmation' => 'abc'], 'password'],
            'contraseña distinta' => [['password_confirmation' => 'otra'], 'password'],
            'contraseña de 500 caracteres' => [['password' => str_repeat('a', 500), 'password_confirmation' => str_repeat('a', 500)], 'password'],
            'sin token del formulario' => [['checkout_token' => null], 'checkout_token'],
            'token que no es uuid' => [['checkout_token' => 'abc'], 'checkout_token'],
        ];
    }

    public function test_los_datos_con_codigo_se_guardan_como_texto_y_se_escapan_al_mostrarse(): void
    {
        $this->cart()->add($this->producto, 1);

        $this->post('/checkout', $this->datos([
            'nombre' => '<script>alert(1)</script>',
            'calle' => "<b onmouseover=alert(3)>Calle'); DROP TABLE orders;--",
            'notas' => '<img src=x onerror=alert(2)>',
        ]))->assertRedirect('/checkout/gracias');

        $orden = Order::sole();
        $this->assertSame('<script>alert(1)</script>', $orden->customer_name);
        $this->assertSame(1, Order::count());   // la inyección SQL no hizo nada

        $this->get(route('portal.pedido', $orden))
            ->assertOk()
            ->assertDontSee('<b onmouseover', false)
            ->assertSee('&lt;b onmouseover=alert(3)&gt;', false);

        // El correo al cliente también escapa el nombre
        $html = (new OrderPlacedMail($orden))->render();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_el_correo_se_normaliza_y_no_permite_cuentas_duplicadas_por_mayusculas(): void
    {
        User::factory()->create(['email' => 'rodrigo@obra.mx', 'role' => UserRole::Client]);
        $this->cart()->add($this->producto, 1);

        $this->post('/checkout', $this->datos(['email' => '  Rodrigo@Obra.MX ']))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::count());
    }

    public function test_el_correo_de_una_cuenta_nueva_se_guarda_en_minusculas(): void
    {
        $this->cart()->add($this->producto, 1);

        $this->post('/checkout', $this->datos(['email' => ' Nuevo@Obra.MX ']))->assertRedirect('/checkout/gracias');

        $this->assertSame('nuevo@obra.mx', User::sole()->email);
        $this->assertSame('nuevo@obra.mx', Order::sole()->customer_email);
    }

    // ---------------- Carrito ----------------

    public function test_cantidades_cero_negativas_o_absurdas_no_rompen_el_carrito(): void
    {
        $this->cart()->add($this->producto, 0);
        $this->cart()->add($this->producto, -5);
        $this->assertTrue($this->cart()->isEmpty());

        $this->cart()->add($this->producto, 9999);
        $this->cart()->add($this->producto, 9999);
        $this->cart()->setQuantity($this->producto->id, 999999999);

        $this->assertLessThanOrEqual(9999, $this->cart()->quantityOf($this->producto->id));
    }

    public function test_un_pedido_gigante_no_revienta_la_base_ni_se_cobra(): void
    {
        $caro = $this->crearProducto('CARO-1', 23000, stock: 100000, extra: ['max_direct_purchase' => 99999]);
        $this->cart()->add($caro, 9999);   // ≈ 230 millones: no cabe en decimal(10,2)

        $this->post('/checkout', $this->datos())->assertSessionHasErrors();

        $this->assertSame(0, Order::count());
        $this->assertSame(100000, $caro->fresh()->stock_qty);
    }

    public function test_un_producto_dado_de_baja_sale_del_carrito_y_el_checkout_lo_ignora(): void
    {
        $this->cart()->add($this->producto, 2);
        $this->producto->update(['is_active' => false]);

        $this->get('/checkout')->assertRedirect('/carrito');
        $this->post('/checkout', $this->datos())->assertRedirect('/carrito');

        $this->assertSame(0, Order::count());
    }

    public function test_un_producto_sin_precio_nunca_se_cobra_en_linea(): void
    {
        $gratis = $this->crearProducto('GRATIS-1', 0);

        $this->assertSame(PurchaseMode::Quote, $gratis->purchaseModeFor(1));

        $this->cart()->add($gratis, 1);
        $this->post('/checkout', $this->datos(['metodo_pago' => null, 'tarjeta' => null]))
            ->assertRedirect('/checkout/gracias');

        $this->assertSame(OrderType::Quote, Order::sole()->order_type);
    }

    public function test_se_cobra_el_precio_vigente_al_confirmar_no_el_que_se_vio_antes(): void
    {
        $this->cart()->add($this->producto, 2);
        $this->producto->update(['price' => 1500]);

        $this->post('/checkout', $this->datos())->assertRedirect('/checkout/gracias');

        $orden = Order::sole();
        $this->assertSame('3000.00', $orden->subtotal);
        $this->assertSame('1500.00', $orden->items()->sole()->unit_price);
    }

    // ---------------- Existencia ----------------

    public function test_se_puede_comprar_exactamente_toda_la_existencia_y_no_mas(): void
    {
        $ultima = $this->crearProducto('ULT-1', 100, stock: 3);
        $this->cart()->add($ultima, 3);

        $this->post('/checkout', $this->datos())->assertRedirect('/checkout/gracias');

        $this->assertSame(0, $ultima->fresh()->stock_qty);
        $this->assertSame(OrderType::DirectSale, Order::sole()->order_type);
    }

    public function test_dos_clientes_por_la_ultima_pieza_el_segundo_va_a_cotizacion(): void
    {
        $ultima = $this->crearProducto('ULT-2', 100, stock: 1);

        $this->cart()->add($ultima, 1);
        $this->post('/checkout', $this->datos(['email' => 'uno@obra.mx']))->assertRedirect('/checkout/gracias');

        // Otro visitante (sesión nueva) llega después: ya no hay existencia
        $this->flushSession();
        auth()->logout();
        $this->app->forgetInstance(Cart::class);
        $this->cart()->add($ultima, 1);

        $this->post('/checkout', $this->datos(['email' => 'dos@obra.mx']))->assertRedirect('/checkout/gracias');

        $this->assertSame(0, $ultima->fresh()->stock_qty);   // nunca negativa
        $this->assertSame(1, Order::sales()->count());
        $this->assertSame(1, Order::quotes()->count());
    }

    public function test_la_existencia_nunca_queda_negativa_aunque_se_fuerce_el_apartado(): void
    {
        $poca = $this->crearProducto('POCA-1', 100, stock: 2);
        $cart = $this->cart();
        $cart->add($poca, 2);
        $this->assertCount(1, $cart->purchasable());   // evaluó la línea como cobrable
        Product::whereKey($poca->id)->update(['stock_qty' => 1]);

        try {
            app(PlaceOrders::class)($cart, new CheckoutData('X', 'x@x.mx', '1'));
            $this->fail('Debió negarse a apartar existencia que ya no hay.');
        } catch (OutOfStockException) {
            // esperado
        }

        $this->assertSame(1, $poca->fresh()->stock_qty);
        $this->assertSame(0, Order::count());
    }

    // ---------------- Totales y folios ----------------

    public function test_los_totales_cuadran_con_redondeo_de_centavos(): void
    {
        $a = $this->crearProducto('RED-1', 33.33, stock: 20);
        $b = $this->crearProducto('RED-2', 19.99, stock: 20);
        $this->cart()->add($a, 3);
        $this->cart()->add($b, 7);

        $this->post('/checkout', $this->datos())->assertRedirect('/checkout/gracias');

        $orden = Order::sole();
        $subtotal = round(33.33 * 3 + 19.99 * 7, 2);   // 239.92
        $iva = round($subtotal * 0.16, 2);

        $this->assertEqualsWithDelta($subtotal, (float) $orden->subtotal, 0.001);
        $this->assertEqualsWithDelta($iva, (float) $orden->tax_amount, 0.001);
        $this->assertEqualsWithDelta($subtotal + $iva, (float) $orden->total_amount, 0.001);
        $this->assertEqualsWithDelta($subtotal, (float) $orden->items()->sum('line_total'), 0.001);
    }

    public function test_compras_seguidas_generan_folios_distintos_y_consecutivos(): void
    {
        $folios = [];

        foreach (range(1, 5) as $i) {
            $this->flushSession();
            auth()->logout();
            $this->app->forgetInstance(Cart::class);
            $this->cart()->add($this->producto, 1);

            $this->post('/checkout', $this->datos(['email' => "cliente{$i}@obra.mx"]))->assertRedirect('/checkout/gracias');
        }

        $folios = Order::orderBy('id')->pluck('folio')->all();

        $this->assertCount(5, array_unique($folios));
        $this->assertSame(
            collect(range(1, 5))->map(fn ($n) => 'VD-'.now()->year.'-'.str_pad((string) $n, 5, '0', STR_PAD_LEFT))->all(),
            $folios,
        );
        $this->assertSame(45, $this->producto->fresh()->stock_qty);
        $this->assertSame(5, User::count());
    }

    // ---------------- Cobros dobles ----------------

    public function test_enviar_dos_veces_el_mismo_formulario_cobra_una_sola_vez(): void
    {
        $this->cart()->add($this->producto, 4);
        $datos = $this->datos();

        $this->post('/checkout', $datos)->assertRedirect('/checkout/gracias');
        $this->post('/checkout', $datos)->assertRedirect('/checkout/gracias');   // mismo token: no duplica

        $this->assertSame(1, Order::count());
        $this->assertSame(1, User::count());
        $this->assertSame(46, $this->producto->fresh()->stock_qty);
        Mail::assertSent(OrderPlacedMail::class, 1);
    }

    public function test_el_reenvio_con_el_mismo_token_muestra_el_mismo_pedido_no_un_error(): void
    {
        $this->cart()->add($this->producto, 1);
        $datos = $this->datos();

        $this->post('/checkout', $datos);
        $this->post('/checkout', $datos);

        $this->get('/checkout/gracias')->assertOk()->assertSee(Order::sole()->folio);
    }

    public function test_dos_envios_simultaneos_el_segundo_espera_en_vez_de_duplicar(): void
    {
        $this->cart()->add($this->producto, 1);
        $datos = $this->datos();

        // Un primer envío sigue en curso: tiene tomado el candado de este formulario
        $candado = Cache::lock('checkout:'.$datos['checkout_token'], 60);
        $this->assertTrue($candado->get());

        $this->post('/checkout', $datos)->assertSessionHasErrors('carrito');

        $this->assertSame(0, Order::count());
        $candado->release();
    }

    public function test_un_formulario_nuevo_si_permite_una_segunda_compra_legitima(): void
    {
        $user = $this->comprador();

        $this->actingAs($user);
        $this->cart()->add($this->producto, 1);
        $this->post('/checkout', $this->datos())->assertRedirect('/checkout/gracias');

        $this->cart()->add($this->producto, 1);
        $this->post('/checkout', $this->datos())->assertRedirect('/checkout/gracias');

        $this->assertSame(2, Order::count());
        $this->assertSame(48, $this->producto->fresh()->stock_qty);
    }

    public function test_pago_rechazado_y_reintento_deja_una_orden_cancelada_y_una_pagada_con_existencia_correcta(): void
    {
        $this->cart()->add($this->producto, 4);

        $this->post('/checkout', $this->datos(['tarjeta' => '4000000000000002']))->assertSessionHasErrors('pago');

        $this->assertSame(50, $this->producto->fresh()->stock_qty);

        $this->post('/checkout', $this->datos())->assertRedirect('/checkout/gracias');

        $this->assertSame(1, Order::where('status', OrderStatus::Cancelled)->count());
        $this->assertSame(1, Order::where('status', OrderStatus::Paid)->count());
        $this->assertSame(46, $this->producto->fresh()->stock_qty);
        $this->assertSame(1, User::count());   // la cuenta no se duplica en el reintento
    }

    // ---------------- Errores forzados ----------------

    public function test_si_la_pasarela_lanza_excepcion_la_orden_se_cancela_y_se_devuelve_la_existencia(): void
    {
        config(['icce.payment.driver' => 'stripe']);   // esbozada: lanza al cobrar
        $this->cart()->add($this->producto, 4);

        $this->post('/checkout', $this->datos())->assertSessionHasErrors('pago');

        $orden = Order::sole();
        $this->assertSame(OrderStatus::Cancelled, $orden->status);
        $this->assertSame(50, $this->producto->fresh()->stock_qty);
        $this->assertFalse($this->cart()->isEmpty());   // el carrito se conserva para reintentar
    }

    public function test_si_falla_el_correo_el_pedido_ya_cobrado_se_conserva(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));
        $this->cart()->add($this->producto, 2);

        $this->post('/checkout', $this->datos())->assertRedirect('/checkout/gracias');

        $this->assertSame(OrderStatus::Paid, Order::sole()->status);
    }

    public function test_la_pasarela_en_produccion_no_cobra_y_no_deja_pedidos_colgados(): void
    {
        // Fuera de 'testing' el CSRF se activa: se manda el token
        $this->app->detectEnvironment(fn () => 'production');
        $this->cart()->add($this->producto, 2);

        $this->withSession(['_token' => 'tok'])
            ->post('/checkout', $this->datos(['_token' => 'tok']))
            ->assertSessionHasErrors('pago');

        $this->assertSame(50, $this->producto->fresh()->stock_qty);
        $this->assertSame(0, Order::where('status', OrderStatus::Paid)->count());
        $this->assertSame(OrderStatus::Cancelled, Order::sole()->status);
    }

    private function cotizacionPorPagar(User $cliente, string $folio, array $extra = []): Order
    {
        return Order::create(array_merge([
            'folio' => $folio, 'user_id' => $cliente->id, 'order_type' => OrderType::Quote,
            'status' => OrderStatus::PendingPayment, 'customer_name' => 'X', 'customer_email' => 'x@x.mx',
            'subtotal' => 1000, 'tax_amount' => 160, 'total_amount' => 1160, 'quote_valid_until' => now()->addDays(5),
        ], $extra));
    }

    public function test_pagar_una_cotizacion_con_pasarela_caida_no_da_error_500_ni_cambia_la_orden(): void
    {
        config(['icce.payment.driver' => 'stripe']);   // lanza al cobrar

        $cliente = $this->comprador();
        $orden = $this->cotizacionPorPagar($cliente, 'COT-2026-00077');

        $this->actingAs($cliente)
            ->post(route('portal.pagar', $orden), ['metodo_pago' => 'card', 'tarjeta' => '4242424242424242'])
            ->assertSessionHasErrors('pago');

        $this->assertSame(OrderStatus::PendingPayment, $orden->fresh()->status);
        $this->assertTrue($orden->fresh()->canBePaidOnline());   // puede reintentar
    }

    public function test_un_carrito_mixto_con_pago_rechazado_no_duplica_la_cotizacion_al_reintentar(): void
    {
        $bajoPedido = $this->crearProducto('BP-1', 500, stock: 0, extra: ['is_on_demand' => true]);
        $this->cart()->add($this->producto, 2);
        $this->cart()->add($bajoPedido, 5);

        $datos = $this->datos(['tarjeta' => '4000000000000002']);
        $this->post('/checkout', $datos)->assertSessionHasErrors('pago');

        $this->assertSame(1, Order::quotes()->count());           // la cotización sí quedó registrada
        $this->assertSame(50, $this->producto->fresh()->stock_qty);
        $this->assertSame(2, $this->cart()->quantityOf($this->producto->id));   // la venta sigue en el carrito
        $this->assertSame(0, $this->cart()->quantityOf($bajoPedido->id));       // la cotización ya no

        $this->post('/checkout', $this->datos(['checkout_token' => $datos['checkout_token']]))
            ->assertRedirect('/checkout/gracias');

        $this->assertSame(1, Order::quotes()->count());           // sigue siendo una sola
        $this->assertSame(1, Order::sales()->where('status', OrderStatus::Paid)->count());
        $this->assertSame(48, $this->producto->fresh()->stock_qty);
    }

    public function test_una_orden_pagada_que_se_regresa_a_pendiente_de_pago_no_se_vuelve_a_cobrar(): void
    {
        $cliente = $this->comprador();
        $orden = $this->cotizacionPorPagar($cliente, 'COT-2026-00078', [
            'status' => OrderStatus::Paid, 'paid_at' => now(), 'payment_status' => 'paid',
        ]);

        // Un agente se equivoca y la regresa a «Pendiente de pago» con «Cambiar estatus»
        app(ChangeOrderStatus::class)($orden, OrderStatus::PendingPayment, notifyCustomer: false);

        $this->assertFalse($orden->fresh()->canBePaidOnline());

        $this->actingAs($cliente)
            ->post(route('portal.pagar', $orden), ['metodo_pago' => 'card', 'tarjeta' => '4242424242424242'])
            ->assertSessionHasErrors('pago');
    }

    public function test_los_campos_extra_del_pago_no_cambian_el_monto_cobrado(): void
    {
        $cliente = $this->comprador();
        $orden = $this->cotizacionPorPagar($cliente, 'COT-2026-00079');

        $this->actingAs($cliente)->post(route('portal.pagar', $orden), [
            'metodo_pago' => 'card', 'tarjeta' => '4242424242424242',
            'total_amount' => 1, 'status' => 'delivered', 'order_type' => 'direct_sale',
        ]);

        $orden = $orden->fresh();
        $this->assertSame('1160.00', $orden->total_amount);
        $this->assertSame(OrderStatus::Paid, $orden->status);
        $this->assertSame(OrderType::Quote, $orden->order_type);
    }

    public function test_pagar_una_cotizacion_con_tarjeta_malformada_se_rechaza_sin_cobrar(): void
    {
        $cliente = $this->comprador();
        $orden = $this->cotizacionPorPagar($cliente, 'COT-2026-00080');

        $this->actingAs($cliente)
            ->post(route('portal.pagar', $orden), ['metodo_pago' => 'card', 'tarjeta' => 'hola'])
            ->assertSessionHasErrors('tarjeta');

        $this->assertSame(OrderStatus::PendingPayment, $orden->fresh()->status);
    }

    // ---------------- Cuenta + pedido en una sola transacción ----------------

    public function test_la_cuenta_se_crea_dentro_de_la_transaccion_del_pedido_y_se_devuelve(): void
    {
        $cart = $this->cart();
        $cart->add($this->producto, 2);

        $placed = app(PlaceOrders::class)(
            $cart,
            new CheckoutData('Ana', 'ana@obra.mx', '1'),
            fn () => User::create([
                'name' => 'Ana', 'email' => 'ana@obra.mx', 'password' => 'Secreto2026',
                'role' => UserRole::Client, 'is_active' => true,
            ]),
        );

        $this->assertSame('ana@obra.mx', $placed->user->email);
        $this->assertSame($placed->user->id, $placed->sale->user_id);
        $this->assertSame(1, User::count());
    }

    public function test_si_el_apartado_falla_la_cuenta_que_se_iba_a_crear_se_revierte(): void
    {
        $poca = $this->crearProducto('POCA-2', 100, stock: 2);
        $cart = $this->cart();
        $cart->add($poca, 2);
        $this->assertCount(1, $cart->purchasable());
        Product::whereKey($poca->id)->update(['stock_qty' => 1]);   // otro cliente se la llevó

        try {
            app(PlaceOrders::class)(
                $cart,
                new CheckoutData('Ana', 'ana@obra.mx', '1'),
                fn () => User::create([
                    'name' => 'Ana', 'email' => 'ana@obra.mx', 'password' => 'Secreto2026',
                    'role' => UserRole::Client, 'is_active' => true,
                ]),
            );
            $this->fail('Debió fallar por falta de existencia.');
        } catch (OutOfStockException) {
            // esperado
        }

        $this->assertSame(0, User::count());
        $this->assertSame(0, Order::count());
        $this->assertSame(1, $poca->fresh()->stock_qty);
    }

    public function test_el_candado_de_registro_se_libera_aunque_el_pedido_falle(): void
    {
        $cart = $this->cart();
        $cart->add($this->producto, 1);

        app(PlaceOrders::class)($cart, new CheckoutData('Ana', 'ana@obra.mx', '1'));

        // Si el candado se hubiera quedado tomado, esto fallaría por timeout
        $lock = Cache::lock('checkout:placement', 5);
        $this->assertTrue($lock->get());
        $lock->release();
    }
}
