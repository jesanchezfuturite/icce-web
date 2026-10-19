<?php

namespace Tests\Feature;

use App\Actions\Orders\RecalculateQuote;
use App\Actions\Orders\SendQuote;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\UserRole;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\QuoteTotals;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cotización en el backoffice: el agente ve el importe de cada renglón y el
 * total con descuento mientras captura, y lo que ve es exactamente lo que se
 * guarda y recibe el cliente (una sola fórmula: QuoteTotals).
 *
 * Caso de la captura de pantalla: 59 × 1,509.90 + 15 × 1,449.90 + 31 × 1,459.90
 * = 156,089.50 de subtotal.
 */
class CotizacionEnVivoTest extends TestCase
{
    use RefreshDatabase;

    private Order $cotizacion;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]));

        $categoria = Category::create(['name' => 'Adaptadores', 'slug' => 'adaptadores']);

        $this->cotizacion = Order::create([
            'folio' => 'COT-2026-00010', 'order_type' => OrderType::Quote, 'status' => OrderStatus::Pending,
            'customer_name' => 'Constructora Prueba', 'customer_email' => 'compras@prueba.mx',
            'subtotal' => 156089.50, 'tax_amount' => 24974.32, 'total_amount' => 181063.82,
        ]);

        foreach ([['ADI-2', 59, 1509.90], ['ADI-6', 15, 1449.90], ['LLA-4', 31, 1459.90]] as [$sku, $cantidad, $precio]) {
            $producto = Product::create([
                'name' => "Producto {$sku}", 'slug' => strtolower($sku), 'sku' => $sku,
                'category_id' => $categoria->id, 'price' => $precio, 'stock_qty' => 0, 'is_active' => true,
            ]);

            $this->cotizacion->items()->create([
                'product_id' => $producto->id, 'product_sku' => $sku, 'product_name' => $producto->name,
                'quantity' => $cantidad, 'unit_price' => $precio, 'line_total' => round($cantidad * $precio, 2),
            ]);
        }
    }

    private function editar()
    {
        return Livewire::test(EditOrder::class, ['record' => $this->cotizacion->getRouteKey()]);
    }

    private function primerRenglon(): string
    {
        return (string) $this->cotizacion->items()->orderBy('id')->value('id');
    }

    // ---------------- La fórmula ----------------

    public function test_la_formula_del_caso_de_la_captura(): void
    {
        $t = QuoteTotals::calculate([[59, 1509.90], [15, 1449.90], [31, 1459.90]]);

        $this->assertSame(156089.50, $t->subtotal);
        $this->assertSame(24974.32, $t->tax);
        $this->assertSame(181063.82, $t->total);
    }

    public function test_el_descuento_se_resta_antes_del_iva_y_nunca_pasa_del_subtotal(): void
    {
        $t = QuoteTotals::calculate([[10, 1000]], 2000);

        $this->assertSame(10000.0, $t->subtotal);
        $this->assertSame(2000.0, $t->discount);
        $this->assertSame(8000.0, $t->taxable);
        $this->assertSame(1280.0, $t->tax);
        $this->assertSame(9280.0, $t->total);

        $tope = QuoteTotals::calculate([[10, 1000]], 999999);
        $this->assertSame(10000.0, $tope->discount);
        $this->assertSame(0.0, $tope->total);
    }

    public function test_valores_vacios_o_raros_valen_cero_y_no_truenan(): void
    {
        $t = QuoteTotals::calculate([['', null], ['abc', '12'], [3, '']], 'xyz');

        $this->assertSame(0.0, $t->subtotal);
        $this->assertSame(0.0, $t->total);
        $this->assertSame(50.0, QuoteTotals::effectivePrice('', 50));
        $this->assertSame(0.0, QuoteTotals::effectivePrice(null, null));
    }

    // ---------------- Detalle de la orden ----------------

    public function test_cada_renglon_muestra_su_importe_y_el_total_sale_sin_guardar(): void
    {
        $this->editar()
            ->assertSee('$89,084.10 MXN')      // 59 × 1,509.90
            ->assertSee('$21,748.50 MXN')      // 15 × 1,449.90
            ->assertSee('$156,089.50 MXN')     // subtotal
            ->assertSee('$181,063.82 MXN');    // total con IVA
    }

    public function test_al_teclear_un_precio_cotizado_el_importe_y_el_total_se_actualizan_al_instante(): void
    {
        $uuid = array_key_first($this->editar()->get('data.items'));

        $this->editar()
            ->set("data.items.{$uuid}.quoted_unit_price", 1400)
            ->assertSee('$82,600.00 MXN')      // 59 × 1,400
            ->assertSee('$149,605.40 MXN')     // subtotal nuevo
            ->assertSee('$173,542.26 MXN');    // total con IVA
    }

    public function test_al_teclear_un_descuento_el_total_cambia_antes_de_guardar(): void
    {
        $this->editar()
            ->set('data.discount_amount', 5000)
            ->assertSee('$156,089.50 MXN')     // subtotal, sin tocar
            ->assertSee('$175,263.82 MXN');    // (156,089.50 − 5,000) × 1.16

        // Nada se guardó todavía
        $this->assertSame('181063.82', $this->cotizacion->fresh()->total_amount);
    }

    public function test_guardar_deja_en_la_orden_exactamente_lo_que_se_vio(): void
    {
        $uuid = array_key_first($this->editar()->get('data.items'));

        $this->editar()
            ->set("data.items.{$uuid}.quoted_unit_price", 1400)
            ->set('data.discount_amount', 5000)
            ->call('save')
            ->assertHasNoFormErrors();

        $orden = $this->cotizacion->fresh();
        $esperado = QuoteTotals::calculate([[59, 1400], [15, 1449.90], [31, 1459.90]], 5000);

        $this->assertSame('149605.40', $orden->subtotal);
        $this->assertSame('5000.00', $orden->discount_amount);
        $this->assertEqualsWithDelta($esperado->tax, (float) $orden->tax_amount, 0.001);
        $this->assertEqualsWithDelta($esperado->total, (float) $orden->total_amount, 0.001);
        $this->assertSame('82600.00', $orden->items()->orderBy('id')->first()->line_total);
        $this->assertSame('1509.90', $orden->items()->orderBy('id')->first()->unit_price);   // lista intacta
    }

    public function test_un_descuento_mayor_al_subtotal_se_guarda_topado(): void
    {
        $this->editar()->set('data.discount_amount', 999999)->call('save');

        $orden = $this->cotizacion->fresh();
        $this->assertSame('156089.50', $orden->discount_amount);
        $this->assertSame('0.00', $orden->total_amount);
    }

    public function test_una_cotizacion_autorizada_o_pagada_ya_no_deja_cambiar_importes(): void
    {
        foreach ([OrderStatus::PendingPayment, OrderStatus::Paid] as $estatus) {
            $this->cotizacion->update(['status' => $estatus]);

            $this->editar()
                ->assertFormFieldIsDisabled('discount_amount')
                ->assertSee('sólo se ajustan mientras la cotización no esté autorizada ni pagada');
        }
    }

    public function test_guardar_una_cotizacion_pagada_no_toca_sus_importes(): void
    {
        $this->cotizacion->update(['status' => OrderStatus::Paid]);

        $this->editar()->call('save');

        $orden = $this->cotizacion->fresh();
        $this->assertSame('156089.50', $orden->subtotal);
        $this->assertSame('181063.82', $orden->total_amount);
    }

    public function test_una_venta_directa_nunca_recalcula_sus_importes(): void
    {
        $this->cotizacion->update(['order_type' => OrderType::DirectSale, 'status' => OrderStatus::Paid]);

        $this->editar()->assertFormFieldIsDisabled('discount_amount');
        $this->assertFalse($this->cotizacion->fresh()->pricesAreEditable());
    }

    // ---------------- Modal «Enviar cotización» ----------------

    public function test_el_desglose_del_modal_muestra_subtotal_descuento_iva_y_total(): void
    {
        // El HTML del modal no se pinta en las pruebas de Livewire: se prueba el
        // desglose que alimenta al modal.
        $sinDescuento = strip_tags((string) OrdersTable::quoteSummary($this->cotizacion, 0));

        $this->assertStringContainsString('$156,089.50 MXN', $sinDescuento);
        $this->assertStringContainsString('$181,063.82 MXN', $sinDescuento);
        $this->assertStringNotContainsString('Subtotal con descuento', $sinDescuento);

        $conDescuento = strip_tags((string) OrdersTable::quoteSummary($this->cotizacion, 10000));

        $this->assertStringContainsString('Descuento', $conDescuento);
        $this->assertStringContainsString('−$10,000.00 MXN', $conDescuento);
        $this->assertStringContainsString('Subtotal con descuento$146,089.50 MXN', $conDescuento);
        $this->assertStringContainsString('IVA (16%)$23,374.32 MXN', $conDescuento);
        $this->assertStringContainsString('Total con descuento$169,463.82 MXN', $conDescuento);
    }

    public function test_el_desglose_usa_los_precios_cotizados_de_las_partidas(): void
    {
        $this->cotizacion->items()->orderBy('id')->first()->update(['quoted_unit_price' => 1400]);

        $resumen = strip_tags((string) OrdersTable::quoteSummary($this->cotizacion, 0));

        $this->assertStringContainsString('$149,605.40 MXN', $resumen);
    }

    public function test_lo_que_mostro_el_modal_es_lo_que_se_envia_y_se_guarda(): void
    {
        Livewire::test(ListOrders::class)
            ->set('activeTab', 'todos')
            ->callTableAction('enviarCotizacion', $this->cotizacion, [
                'discount_amount' => 10000, 'valid_until' => now()->addDays(10)->toDateString(),
            ])
            ->assertHasNoTableActionErrors();

        $orden = $this->cotizacion->fresh();

        $this->assertSame(OrderStatus::Quoted, $orden->status);
        $this->assertSame('156089.50', $orden->subtotal);
        $this->assertSame('10000.00', $orden->discount_amount);
        $this->assertEqualsWithDelta(169463.82, (float) $orden->total_amount, 0.001);
    }

    public function test_enviar_y_recalcular_dan_el_mismo_resultado(): void
    {
        $this->cotizacion->items()->orderBy('id')->first()->update(['quoted_unit_price' => 1400]);

        $recalculado = app(RecalculateQuote::class)($this->cotizacion->fresh(), 5000);
        $totalGuardado = $this->cotizacion->fresh()->total_amount;

        app(SendQuote::class)($this->cotizacion->fresh(), null, null, null, 5000);

        $this->assertEqualsWithDelta($recalculado->total, (float) $this->cotizacion->fresh()->total_amount, 0.001);
        $this->assertSame($totalGuardado, $this->cotizacion->fresh()->total_amount);
    }
}
