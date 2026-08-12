<?php

namespace Tests\Feature;

use App\Actions\Orders\ChangeOrderStatus;
use App\Actions\Orders\SendQuote;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\UserRole;
use App\Mail\OrderStatusChangedMail;
use App\Mail\QuoteSentMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Operación del backoffice: mover estatus (REQ-05) y trabajar una cotización
 * (REQ-09). Se prueban las acciones de dominio, no la capa de Filament: son
 * ellas las que garantizan que bitácora, fechas y avisos ocurran juntos.
 */
class CrmOperacionTest extends TestCase
{
    use RefreshDatabase;

    private User $agente;

    private Order $venta;

    private Order $cotizacion;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->agente = User::factory()->create(['role' => UserRole::Sales, 'name' => 'Agente de Ventas']);
        $category = Category::create(['name' => 'Llanas', 'slug' => 'llanas']);

        $product = Product::create([
            'name' => 'Llana de acabado', 'slug' => 'llana', 'sku' => 'LL-001',
            'category_id' => $category->id, 'price' => 1000, 'stock_qty' => 50, 'is_active' => true,
        ]);

        $this->venta = Order::create([
            'folio' => 'VD-2026-00001', 'order_type' => OrderType::DirectSale,
            'status' => OrderStatus::Paid, 'customer_name' => 'Constructora Prueba',
            'customer_email' => 'compras@prueba.mx', 'subtotal' => 1000,
            'tax_amount' => 160, 'total_amount' => 1160,
        ]);
        $this->venta->items()->create([
            'product_id' => $product->id, 'product_sku' => 'LL-001', 'product_name' => 'Llana de acabado',
            'quantity' => 1, 'unit_price' => 1000, 'line_total' => 1000,
        ]);

        $this->cotizacion = Order::create([
            'folio' => 'COT-2026-00001', 'order_type' => OrderType::Quote,
            'status' => OrderStatus::Pending, 'customer_name' => 'Constructora Prueba',
            'customer_email' => 'compras@prueba.mx', 'subtotal' => 40000,
            'tax_amount' => 6400, 'total_amount' => 46400,
        ]);
        $this->cotizacion->items()->create([
            'product_id' => $product->id, 'product_sku' => 'LL-001', 'product_name' => 'Llana de acabado',
            'quantity' => 40, 'unit_price' => 1000, 'line_total' => 40000,
        ]);
    }

    // ---------------- REQ-05: estatus y entrega ----------------

    public function test_cambiar_estatus_deja_bitacora_y_sella_la_fecha(): void
    {
        app(ChangeOrderStatus::class)(
            order: $this->venta,
            to: OrderStatus::Shipped,
            author: $this->agente,
            note: 'Sale hoy en la ruta de Monterrey.',
            trackingNumber: 'ICCE2600123',
        );

        $this->venta->refresh();

        $this->assertSame(OrderStatus::Shipped, $this->venta->status);
        $this->assertNotNull($this->venta->shipped_at);
        $this->assertSame('ICCE2600123', $this->venta->tracking_number);

        $history = $this->venta->statusHistories()->latest('id')->first();
        $this->assertSame(OrderStatus::Paid, $history->from_status);
        $this->assertSame(OrderStatus::Shipped, $history->to_status);
        $this->assertSame($this->agente->id, $history->user_id);
        $this->assertTrue($history->notified_customer);
    }

    public function test_la_fecha_de_entrega_queda_visible_para_el_cliente(): void
    {
        app(ChangeOrderStatus::class)(
            order: $this->venta,
            to: OrderStatus::Processing,
            estimatedDeliveryDate: '2026-09-15',
        );

        $this->assertSame('2026-09-15', $this->venta->refresh()->estimated_delivery_date->toDateString());
    }

    public function test_se_avisa_al_cliente_cuando_se_pide(): void
    {
        app(ChangeOrderStatus::class)(order: $this->venta, to: OrderStatus::Shipped, notifyCustomer: true);

        Mail::assertSent(OrderStatusChangedMail::class, fn ($m) => $m->hasTo('compras@prueba.mx'));
    }

    public function test_se_puede_mover_sin_avisar(): void
    {
        app(ChangeOrderStatus::class)(order: $this->venta, to: OrderStatus::Processing, notifyCustomer: false);

        Mail::assertNothingSent();
        $this->assertFalse($this->venta->statusHistories()->latest('id')->first()->notified_customer);
    }

    public function test_repetir_el_mismo_estatus_no_ensucia_la_bitacora(): void
    {
        app(ChangeOrderStatus::class)(order: $this->venta, to: OrderStatus::Paid);

        $this->assertSame(0, $this->venta->statusHistories()->count());
        Mail::assertNothingSent();
    }

    public function test_el_timeline_del_cliente_refleja_el_avance(): void
    {
        foreach ([OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered] as $estatus) {
            app(ChangeOrderStatus::class)(order: $this->venta, to: $estatus, author: $this->agente);
        }

        $this->assertSame(3, $this->venta->statusHistories()->count());
        $this->assertSame(4, $this->venta->refresh()->status->trackingPosition());
        $this->assertNotNull($this->venta->delivered_at);
    }

    // ---------------- REQ-09: cotización ----------------

    public function test_enviar_cotizacion_recalcula_con_los_precios_ajustados(): void
    {
        // El agente baja el unitario de $1,000 a $820
        $this->cotizacion->items()->first()->update(['quoted_unit_price' => 820]);

        app(SendQuote::class)($this->cotizacion, $this->agente, 'Precio de proyecto por volumen.');

        $this->cotizacion->refresh();

        $this->assertSame('32800.00', $this->cotizacion->subtotal);
        $this->assertSame('5248.00', $this->cotizacion->tax_amount);
        $this->assertSame('38048.00', $this->cotizacion->total_amount);
        $this->assertSame(OrderStatus::Quoted, $this->cotizacion->status);
        $this->assertNotNull($this->cotizacion->quoted_at);
    }

    public function test_el_precio_de_lista_se_conserva_para_auditar_el_descuento(): void
    {
        $this->cotizacion->items()->first()->update(['quoted_unit_price' => 820]);
        app(SendQuote::class)($this->cotizacion, $this->agente);

        $item = $this->cotizacion->items()->first();

        $this->assertSame('1000.00', $item->unit_price);
        $this->assertSame('820.00', $item->quoted_unit_price);
        $this->assertSame('820.00', $item->effectiveUnitPrice());
    }

    public function test_el_descuento_global_se_aplica_antes_del_iva(): void
    {
        app(SendQuote::class)($this->cotizacion, $this->agente, discountAmount: 4000);

        $this->cotizacion->refresh();

        $this->assertSame('40000.00', $this->cotizacion->subtotal);
        $this->assertSame('4000.00', $this->cotizacion->discount_amount);
        $this->assertSame('5760.00', $this->cotizacion->tax_amount);   // 36 000 × 16 %
        $this->assertSame('41760.00', $this->cotizacion->total_amount);
    }

    public function test_el_descuento_no_puede_superar_el_subtotal(): void
    {
        app(SendQuote::class)($this->cotizacion, $this->agente, discountAmount: 999999);

        $this->assertSame('40000.00', $this->cotizacion->refresh()->discount_amount);
        $this->assertSame('0.00', $this->cotizacion->total_amount);
    }

    public function test_la_cotizacion_llega_al_cliente_con_su_pdf(): void
    {
        app(SendQuote::class)($this->cotizacion, $this->agente, 'Adjunto propuesta.');

        Mail::assertSent(QuoteSentMail::class, function (QuoteSentMail $mail) {
            $adjuntos = $mail->attachments();

            return $mail->hasTo('compras@prueba.mx')
                && count($adjuntos) === 1
                && str_ends_with($adjuntos[0]->as, 'COT-2026-00001.pdf');
        });
    }

    public function test_el_pdf_se_genera_y_es_un_pdf_valido(): void
    {
        $salida = app(SendQuote::class)->pdf($this->cotizacion)->output();

        $this->assertStringStartsWith('%PDF-', $salida);
        $this->assertGreaterThan(1000, strlen($salida));
    }

    public function test_la_vigencia_por_omision_es_de_quince_dias(): void
    {
        app(SendQuote::class)($this->cotizacion, $this->agente);

        $this->assertSame(
            now()->addDays(15)->toDateString(),
            $this->cotizacion->refresh()->quote_valid_until->toDateString(),
        );
    }
}
