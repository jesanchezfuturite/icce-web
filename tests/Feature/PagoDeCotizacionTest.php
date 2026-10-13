<?php

namespace Tests\Feature;

use App\Actions\Orders\AuthorizeQuote;
use App\Actions\Orders\RegisterOfflinePayment;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\UserRole;
use App\Mail\OrderStatusChangedMail;
use App\Models\Order;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Cotización autorizada → «Pendiente de pago» → el cliente paga desde su portal
 * una sola vez; o el agente registra un pago hecho fuera de la plataforma.
 */
class PagoDeCotizacionTest extends TestCase
{
    use RefreshDatabase;

    private User $agente;

    private User $cliente;

    private Order $cotizacion;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->agente = User::factory()->create(['role' => UserRole::Sales]);
        $this->cliente = User::factory()->create(['role' => UserRole::Client]);

        // Cotización ya enviada: 10 piezas con descuento de proyecto
        $this->cotizacion = Order::create([
            'folio' => 'COT-2026-00001', 'user_id' => $this->cliente->id,
            'order_type' => OrderType::Quote, 'status' => OrderStatus::Quoted,
            'customer_name' => 'Constructora Prueba', 'customer_email' => 'compras@prueba.mx',
            'subtotal' => 8000, 'discount_amount' => 500, 'tax_amount' => 1200, 'total_amount' => 8700,
            'quote_valid_until' => now()->addDays(10),
        ]);
    }

    private function autorizar(): void
    {
        app(AuthorizeQuote::class)($this->cotizacion, $this->agente);
        $this->cotizacion->refresh();
    }

    private function pagar(array $extra = [])
    {
        return $this->actingAs($this->cliente)->post(
            route('portal.pagar', $this->cotizacion),
            array_merge(['metodo_pago' => 'card', 'tarjeta' => '4242424242424242'], $extra),
        );
    }

    // ---------------- Habilitar el pago ----------------

    public function test_autorizar_una_cotizacion_la_deja_pendiente_de_pago_y_avisa_con_el_enlace(): void
    {
        $this->autorizar();

        $this->assertSame(OrderStatus::PendingPayment, $this->cotizacion->status);
        $this->assertTrue($this->cotizacion->canBePaidOnline());

        $historia = $this->cotizacion->statusHistories()->latest('id')->first();
        $this->assertSame(OrderStatus::Quoted, $historia->from_status);
        $this->assertSame(OrderStatus::PendingPayment, $historia->to_status);

        Mail::assertSent(OrderStatusChangedMail::class, function ($mail) {
            return $mail->hasTo('compras@prueba.mx')
                && str_contains($mail->render(), route('portal.pedido', $this->cotizacion));
        });
    }

    public function test_solo_se_autoriza_una_cotizacion_que_ya_se_envio(): void
    {
        $this->cotizacion->update(['status' => OrderStatus::Pending]);

        $this->expectException(DomainException::class);

        app(AuthorizeQuote::class)($this->cotizacion->fresh(), $this->agente);
    }

    public function test_una_venta_directa_no_se_puede_autorizar_como_cotizacion(): void
    {
        $this->cotizacion->update(['order_type' => OrderType::DirectSale]);

        $this->expectException(DomainException::class);

        app(AuthorizeQuote::class)($this->cotizacion->fresh(), $this->agente);
    }

    public function test_pendiente_de_pago_se_dibuja_sobre_cotizado_en_el_timeline(): void
    {
        $this->assertSame(
            OrderStatus::Quoted->trackingPosition(),
            OrderStatus::PendingPayment->trackingPosition(),
        );
    }

    // ---------------- Pago del cliente ----------------

    public function test_una_cotizacion_sin_autorizar_no_muestra_boton_de_pago_ni_se_puede_pagar(): void
    {
        $this->actingAs($this->cliente)->get(route('portal.pedido', $this->cotizacion))
            ->assertOk()
            ->assertDontSee('Pagar esta cotización');

        $this->pagar()->assertSessionHasErrors('pago');

        $this->assertSame(OrderStatus::Quoted, $this->cotizacion->fresh()->status);
    }

    public function test_el_cliente_ve_el_boton_y_paga_el_total_autorizado(): void
    {
        $this->autorizar();

        $this->actingAs($this->cliente)->get(route('portal.pedido', $this->cotizacion))
            ->assertOk()
            ->assertSee('Pagar esta cotización')
            ->assertSee('8,700.00');

        $this->pagar()->assertRedirect(route('portal.pedido', $this->cotizacion));

        $orden = $this->cotizacion->fresh();
        $this->assertSame(OrderStatus::Paid, $orden->status);
        $this->assertNotNull($orden->paid_at);
        $this->assertSame('paid', $orden->payment_status);
        $this->assertSame('8700.00', $orden->total_amount);
    }

    public function test_el_pago_es_de_una_sola_vez(): void
    {
        $this->autorizar();

        $this->pagar()->assertSessionHasNoErrors();
        $this->pagar()->assertSessionHasErrors('pago');

        // Un solo asiento de pago en la bitácora, no dos
        $this->assertSame(1, $this->cotizacion->statusHistories()->where('to_status', OrderStatus::Paid->value)->count());

        $this->actingAs($this->cliente)->get(route('portal.pedido', $this->cotizacion))
            ->assertDontSee('Pagar esta cotización');
    }

    public function test_un_rechazo_no_gasta_la_autorizacion_y_permite_reintentar(): void
    {
        $this->autorizar();

        $this->pagar(['tarjeta' => '4000000000000002'])->assertSessionHasErrors('pago');
        $this->assertSame(OrderStatus::PendingPayment, $this->cotizacion->fresh()->status);

        $this->pagar()->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Paid, $this->cotizacion->fresh()->status);
    }

    public function test_una_cotizacion_vencida_ya_no_se_puede_pagar(): void
    {
        $this->autorizar();
        $this->cotizacion->update(['quote_valid_until' => now()->subDay()]);

        $this->assertFalse($this->cotizacion->fresh()->canBePaidOnline());

        $this->pagar()->assertSessionHasErrors('pago');
        $this->assertSame(OrderStatus::PendingPayment, $this->cotizacion->fresh()->status);
    }

    public function test_el_ultimo_dia_de_vigencia_todavia_se_puede_pagar(): void
    {
        $this->autorizar();
        $this->cotizacion->update(['quote_valid_until' => now()->toDateString()]);

        $this->assertTrue($this->cotizacion->fresh()->canBePaidOnline());
    }

    public function test_spei_queda_registrado_y_bloquea_un_segundo_intento(): void
    {
        $this->autorizar();

        $this->pagar(['metodo_pago' => 'spei', 'tarjeta' => null])->assertSessionHasNoErrors();

        $orden = $this->cotizacion->fresh();
        $this->assertSame(OrderStatus::PendingPayment, $orden->status);
        $this->assertSame('pending', $orden->payment_status);
        $this->assertFalse($orden->canBePaidOnline());
    }

    public function test_otro_cliente_no_puede_pagar_una_cotizacion_ajena(): void
    {
        $this->autorizar();
        $intruso = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($intruso)->post(route('portal.pagar', $this->cotizacion), [
            'metodo_pago' => 'card', 'tarjeta' => '4242424242424242',
        ])->assertNotFound();

        $this->assertSame(OrderStatus::PendingPayment, $this->cotizacion->fresh()->status);
    }

    public function test_pagar_exige_iniciar_sesion(): void
    {
        $this->post(route('portal.pagar', $this->cotizacion), ['metodo_pago' => 'card'])
            ->assertRedirect(route('login'));
    }

    // ---------------- Pago fuera de la plataforma ----------------

    public function test_el_agente_puede_marcar_pagada_sin_pasar_por_pendiente_de_pago(): void
    {
        app(RegisterOfflinePayment::class)(
            $this->cotizacion, 'transferencia', 'SPEI 998877', $this->agente,
        );

        $orden = $this->cotizacion->fresh();

        $this->assertSame(OrderStatus::Paid, $orden->status);
        $this->assertNotNull($orden->paid_at);
        $this->assertSame('manual', $orden->payment_provider);
        $this->assertStringContainsString('SPEI 998877', $orden->payment_reference);

        // Quoted → Paid directo: nunca hubo asiento de «Pendiente de pago»
        $this->assertSame(0, $orden->statusHistories()->where('to_status', OrderStatus::PendingPayment->value)->count());
        $this->assertSame($this->agente->id, $orden->statusHistories()->latest('id')->first()->user_id);
    }

    public function test_pagar_por_fuera_cierra_la_puerta_al_pago_en_linea(): void
    {
        $this->autorizar();

        app(RegisterOfflinePayment::class)($this->cotizacion, 'efectivo', null, $this->agente);

        $this->assertFalse($this->cotizacion->fresh()->canBePaidOnline());
        $this->pagar()->assertSessionHasErrors('pago');
    }

    public function test_no_se_registra_dos_veces_el_pago_de_una_orden(): void
    {
        app(RegisterOfflinePayment::class)($this->cotizacion, 'transferencia', null, $this->agente);

        $this->expectException(DomainException::class);

        app(RegisterOfflinePayment::class)($this->cotizacion->fresh(), 'transferencia', null, $this->agente);
    }
}
