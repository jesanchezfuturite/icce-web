<?php

namespace Tests\Feature;

use App\Actions\Checkout\CheckoutData;
use App\Actions\Checkout\OutOfStockException;
use App\Actions\Checkout\PlaceOrders;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Mail\OrderPlacedMail;
use App\Mail\OrderReceivedNotification;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Cart\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Carrito híbrido y checkout (fase 4). Catálogo mínimo y controlado: las
 * aserciones deben depender de la regla, no del contenido del cliente.
 */
class CarritoHibridoTest extends TestCase
{
    use RefreshDatabase;

    private Product $enExistencia;

    private Product $bajoPedido;

    private Product $equipoRenta;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $brand = Brand::create(['name' => 'Kraft Tool', 'slug' => 'kraft-tool']);
        $category = Category::create(['name' => 'Llanas', 'slug' => 'llanas']);

        $this->enExistencia = Product::create([
            'name' => 'Llana de acabado', 'slug' => 'llana-de-acabado', 'sku' => 'LL-001',
            'category_id' => $category->id, 'brand_id' => $brand->id,
            'price' => 1000, 'stock_qty' => 50, 'max_direct_purchase' => 10,
            'is_active' => true, 'is_for_sale' => true, 'unit' => 'pieza',
        ]);

        $this->bajoPedido = Product::create([
            'name' => 'Sellador especial', 'slug' => 'sellador-especial', 'sku' => 'SE-001',
            'category_id' => $category->id,
            'price' => 2000, 'stock_qty' => 0, 'is_on_demand' => true,
            'is_active' => true, 'is_for_sale' => true, 'unit' => 'cubeta',
        ]);

        $this->equipoRenta = Product::create([
            'name' => 'Regla láser S-940', 'slug' => 'regla-laser-s940', 'sku' => 'RL-940',
            'category_id' => $category->id,
            'price' => 0, 'stock_qty' => 2,
            'is_active' => true, 'is_for_sale' => false, 'is_rental' => true, 'unit' => 'equipo',
        ]);
    }

    private function cart(): Cart
    {
        return app(Cart::class);
    }

    private function datosValidos(array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Ing. Rodrigo Cantú',
            'email' => 'rodrigo@obra.mx',
            'telefono' => '8112345678',
            'calle' => 'Av. Barragán 1234',
            'ciudad' => 'Monterrey',
            'estado' => 'Nuevo León',
            'cp' => '64000',
            'metodo_pago' => 'card',
            'tarjeta' => '4242424242424242',
        ], $extra);
    }

    // ---------------- Partición del carrito ----------------

    public function test_una_cantidad_dentro_del_limite_es_cobrable(): void
    {
        $this->cart()->add($this->enExistencia, 5);

        $this->assertCount(1, $this->cart()->purchasable());
        $this->assertCount(0, $this->cart()->quotable());
    }

    public function test_rebasar_el_limite_manda_la_linea_a_cotizacion(): void
    {
        $this->cart()->add($this->enExistencia, 40);

        $this->assertCount(0, $this->cart()->purchasable());
        $this->assertCount(1, $this->cart()->quotable());
        $this->assertStringContainsString(
            'precio de proyecto',
            $this->cart()->quotable()->first()->quoteReason(),
        );
    }

    public function test_bajo_pedido_siempre_va_a_cotizacion(): void
    {
        $this->cart()->add($this->bajoPedido, 1);

        $this->assertCount(1, $this->cart()->quotable());
    }

    public function test_un_equipo_de_renta_nunca_es_cobrable(): void
    {
        $this->cart()->add($this->equipoRenta, 1);

        $this->assertCount(0, $this->cart()->purchasable());
    }

    public function test_el_carrito_mixto_se_reconoce_como_tal(): void
    {
        $this->cart()->add($this->enExistencia, 2);
        $this->cart()->add($this->bajoPedido, 5);

        $this->assertTrue($this->cart()->isMixed());
    }

    public function test_el_modo_se_recalcula_si_cambia_la_existencia(): void
    {
        $this->cart()->add($this->enExistencia, 8);
        $this->assertCount(1, $this->cart()->purchasable());

        // El almacén se queda con menos piezas de las que el cliente pidió
        $this->enExistencia->update(['stock_qty' => 3]);

        // Instancia nueva = petición nueva. Dentro de una misma petición el
        // carrito cachea sus líneas a propósito, para no repetir la consulta.
        $siguientePeticion = new Cart(app('session.store'));

        $this->assertCount(0, $siguientePeticion->purchasable());
        $this->assertCount(1, $siguientePeticion->quotable());
    }

    public function test_un_producto_dado_de_baja_desaparece_del_carrito(): void
    {
        $this->cart()->add($this->enExistencia, 1);
        $this->enExistencia->update(['is_active' => false]);

        $this->assertTrue(app(Cart::class)->isEmpty());
    }

    public function test_sumar_el_mismo_producto_acumula_cantidad(): void
    {
        $this->cart()->add($this->enExistencia, 2);
        $this->cart()->add($this->enExistencia, 3);

        $this->assertSame(5, $this->cart()->quantityOf($this->enExistencia->id));
    }

    public function test_poner_cantidad_cero_quita_la_linea(): void
    {
        $this->cart()->add($this->enExistencia, 2);
        $this->cart()->setQuantity($this->enExistencia->id, 0);

        $this->assertTrue($this->cart()->isEmpty());
    }

    // ---------------- Checkout ----------------

    public function test_el_checkout_vacio_regresa_al_carrito(): void
    {
        $this->get('/checkout')->assertRedirect('/carrito');
    }

    public function test_una_compra_directa_genera_orden_pagada_y_descuenta_existencia(): void
    {
        $this->cart()->add($this->enExistencia, 4);

        $this->post('/checkout', $this->datosValidos())->assertRedirect('/checkout/gracias');

        $order = Order::sole();
        $this->assertSame(OrderType::DirectSale, $order->order_type);
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('simulado', $order->payment_provider);
        $this->assertNotNull($order->paid_at);

        $this->assertSame(46, $this->enExistencia->fresh()->stock_qty);
        $this->assertTrue(app(Cart::class)->isEmpty());
    }

    public function test_una_cotizacion_no_cobra_ni_toca_existencia(): void
    {
        $this->cart()->add($this->bajoPedido, 30);

        $this->post('/checkout', $this->datosValidos(['metodo_pago' => null, 'tarjeta' => null]))
            ->assertRedirect('/checkout/gracias');

        $order = Order::sole();
        $this->assertSame(OrderType::Quote, $order->order_type);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertNull($order->payment_provider);
        $this->assertNotNull($order->quote_valid_until);
    }

    public function test_un_carrito_mixto_produce_dos_ordenes(): void
    {
        $this->cart()->add($this->enExistencia, 3);
        $this->cart()->add($this->bajoPedido, 40);

        $this->post('/checkout', $this->datosValidos())->assertRedirect('/checkout/gracias');

        $this->assertSame(2, Order::count());
        $this->assertSame(OrderStatus::Paid, Order::sales()->sole()->status);
        $this->assertSame(OrderStatus::Pending, Order::quotes()->sole()->status);

        // Cada orden lleva sólo lo suyo
        $this->assertSame('LL-001', Order::sales()->sole()->items()->value('product_sku'));
        $this->assertSame('SE-001', Order::quotes()->sole()->items()->value('product_sku'));
    }

    public function test_la_partida_cotizada_guarda_por_que_lo_fue(): void
    {
        $this->cart()->add($this->bajoPedido, 5);
        $this->post('/checkout', $this->datosValidos(['metodo_pago' => null, 'tarjeta' => null]));

        $this->assertStringContainsString('bajo pedido', Order::sole()->items()->value('notes'));
    }

    public function test_un_pago_rechazado_cancela_la_orden_y_devuelve_la_existencia(): void
    {
        $this->cart()->add($this->enExistencia, 4);

        // Tarjeta reservada por la pasarela simulada para el camino de rechazo
        $this->post('/checkout', $this->datosValidos(['tarjeta' => '4000000000000002']))
            ->assertSessionHasErrors('pago');

        $this->assertSame(OrderStatus::Cancelled, Order::sole()->status);
        $this->assertSame(50, $this->enExistencia->fresh()->stock_qty);
        // El carrito se conserva para que el cliente reintente
        $this->assertFalse(app(Cart::class)->isEmpty());
    }

    public function test_spei_deja_la_orden_pendiente_de_acreditacion(): void
    {
        $this->cart()->add($this->enExistencia, 2);

        $this->post('/checkout', $this->datosValidos(['metodo_pago' => 'spei', 'tarjeta' => null]));

        $order = Order::sole();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertNull($order->paid_at);
    }

    public function test_si_baja_la_existencia_la_linea_pasa_a_cotizacion_en_vez_de_fallar(): void
    {
        $this->cart()->add($this->enExistencia, 8);

        // Entre armar el carrito y confirmar, el almacén se quedó corto
        Product::whereKey($this->enExistencia->id)->update(['stock_qty' => 2]);

        $this->post('/checkout', $this->datosValidos())->assertRedirect('/checkout/gracias');

        // No se vende lo que no hay, pero tampoco se pierde el pedido:
        // entra como cotización y un agente confirma el surtido.
        $this->assertSame(0, Order::sales()->count());
        $this->assertSame(1, Order::quotes()->count());
        $this->assertSame(2, $this->enExistencia->fresh()->stock_qty);
    }

    public function test_dos_compras_simultaneas_no_venden_la_misma_pieza(): void
    {
        // El carrito ya evaluó la línea como cobrable...
        $cart = new Cart(app('session.store'));
        $cart->add($this->enExistencia, 8);
        $this->assertCount(1, $cart->purchasable());

        // ...y otro checkout se lleva el inventario antes de que este confirme.
        Product::whereKey($this->enExistencia->id)->update(['stock_qty' => 2]);

        $this->expectException(OutOfStockException::class);

        app(PlaceOrders::class)($cart, new CheckoutData(
            name: 'Ing. Rodrigo Cantú',
            email: 'rodrigo@obra.mx',
            phone: '8112345678',
        ));
    }

    public function test_la_orden_se_liga_al_cliente_que_inicio_sesion(): void
    {
        $user = User::factory()->create();
        $this->cart()->add($this->enExistencia, 1);

        $this->actingAs($user)->post('/checkout', $this->datosValidos());

        $this->assertSame($user->id, Order::sole()->user_id);
    }

    public function test_el_checkout_valida_los_datos_obligatorios(): void
    {
        $this->cart()->add($this->enExistencia, 1);

        $this->post('/checkout', ['nombre' => 'Solo el nombre'])
            ->assertSessionHasErrors(['email', 'telefono', 'calle', 'ciudad', 'estado', 'cp']);

        $this->assertSame(0, Order::count());
    }

    // ---------------- Correos ----------------

    public function test_se_notifica_al_cliente_y_a_ventas(): void
    {
        $this->cart()->add($this->enExistencia, 2);
        $this->post('/checkout', $this->datosValidos());

        Mail::assertSent(OrderPlacedMail::class, fn ($mail) => $mail->hasTo('rodrigo@obra.mx'));
        Mail::assertSent(OrderReceivedNotification::class, fn ($mail) => $mail->hasTo(config('icce.sales_email')));
    }

    public function test_un_carrito_mixto_notifica_por_cada_orden(): void
    {
        $this->cart()->add($this->enExistencia, 2);
        $this->cart()->add($this->bajoPedido, 20);

        $this->post('/checkout', $this->datosValidos());

        Mail::assertSent(OrderPlacedMail::class, 2);
        Mail::assertSent(OrderReceivedNotification::class, 2);
    }

    public function test_la_pantalla_de_gracias_exige_haber_comprado(): void
    {
        $this->get('/checkout/gracias')->assertRedirect('/catalogo');
    }
}
