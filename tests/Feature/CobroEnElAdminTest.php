<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\UserRole;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Las acciones del listado «Pedidos y cotizaciones» para cobrar: habilitar el
 * pago de una cotización autorizada y registrar un pago hecho por fuera.
 */
class CobroEnElAdminTest extends TestCase
{
    use RefreshDatabase;

    private Order $cotizada;

    private Order $entregada;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]));

        $this->cotizada = $this->orden('COT-2026-00001', OrderType::Quote, OrderStatus::Quoted);
        $this->entregada = $this->orden('VD-2026-00001', OrderType::DirectSale, OrderStatus::Delivered);
    }

    private function orden(string $folio, OrderType $tipo, OrderStatus $estatus): Order
    {
        return Order::create([
            'folio' => $folio, 'order_type' => $tipo, 'status' => $estatus,
            'customer_name' => 'Cliente '.$folio, 'customer_email' => 'compras@prueba.mx',
            'subtotal' => 1000, 'tax_amount' => 160, 'total_amount' => 1160,
            'quote_valid_until' => now()->addDays(5),
        ]);
    }

    public function test_habilitar_pago_solo_aparece_en_una_cotizacion_ya_enviada(): void
    {
        $pendiente = $this->orden('COT-2026-00002', OrderType::Quote, OrderStatus::Pending);

        Livewire::test(ListOrders::class)
            ->set('activeTab', 'todos')
            ->assertTableActionVisible('autorizarCotizacion', $this->cotizada)
            ->assertTableActionHidden('autorizarCotizacion', $pendiente)
            ->assertTableActionHidden('autorizarCotizacion', $this->entregada);
    }

    public function test_habilitar_pago_deja_la_cotizacion_pendiente_de_pago(): void
    {
        Livewire::test(ListOrders::class)
            ->set('activeTab', 'todos')
            ->callTableAction('autorizarCotizacion', $this->cotizada, [
                'valid_until' => now()->addDays(7)->toDateString(),
                'notify' => true,
            ])
            ->assertHasNoTableActionErrors();

        $orden = $this->cotizada->fresh();

        $this->assertSame(OrderStatus::PendingPayment, $orden->status);
        $this->assertTrue($orden->canBePaidOnline());
        $this->assertSame(now()->addDays(7)->toDateString(), $orden->quote_valid_until->toDateString());
    }

    public function test_registrar_pago_pasa_directo_a_pagado(): void
    {
        Livewire::test(ListOrders::class)
            ->set('activeTab', 'todos')
            ->callTableAction('registrarPago', $this->cotizada, [
                'method' => 'transferencia', 'reference' => 'CLAVE 123', 'notify' => false,
            ])
            ->assertHasNoTableActionErrors();

        $orden = $this->cotizada->fresh();

        $this->assertSame(OrderStatus::Paid, $orden->status);
        $this->assertSame('manual', $orden->payment_provider);
        $this->assertSame(0, $orden->statusHistories()->where('to_status', OrderStatus::PendingPayment->value)->count());
    }

    public function test_registrar_pago_no_aparece_en_una_orden_ya_cobrada(): void
    {
        Livewire::test(ListOrders::class)
            ->set('activeTab', 'todos')
            ->assertTableActionHidden('registrarPago', $this->entregada)
            ->assertTableActionVisible('registrarPago', $this->cotizada);
    }

    public function test_hay_una_pestana_de_pendientes_de_pago(): void
    {
        $this->cotizada->update(['status' => OrderStatus::PendingPayment]);

        Livewire::test(ListOrders::class)
            ->set('activeTab', 'por_cobrar')
            ->assertCanSeeTableRecords([$this->cotizada])
            ->assertCanNotSeeTableRecords([$this->entregada]);
    }
}
