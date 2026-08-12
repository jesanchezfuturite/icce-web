<?php

namespace Tests\Unit;

use App\Enums\PurchaseMode;
use App\Models\Product;
use PHPUnit\Framework\TestCase;

/**
 * Motor de decisión del carrito híbrido (REQ-01 / REQ-02).
 * No toca base de datos: la regla es pura y debe poder probarse sin ella.
 */
class PurchaseModeTest extends TestCase
{
    private function product(array $attributes = []): Product
    {
        return new Product([
            'stock_qty' => 100,
            'max_direct_purchase' => 10,
            'is_on_demand' => false,
            'is_for_sale' => true,
            ...$attributes,
        ]);
    }

    public function test_cobra_en_linea_dentro_del_limite(): void
    {
        $product = $this->product();

        $this->assertSame(PurchaseMode::Buy, $product->purchaseModeFor(1));
        $this->assertSame(PurchaseMode::Buy, $product->purchaseModeFor(10));
    }

    public function test_convierte_a_cotizacion_al_rebasar_el_limite(): void
    {
        $this->assertSame(PurchaseMode::Quote, $this->product()->purchaseModeFor(11));
    }

    public function test_respeta_un_limite_propio_del_producto(): void
    {
        $product = $this->product(['max_direct_purchase' => 3]);

        $this->assertSame(PurchaseMode::Buy, $product->purchaseModeFor(3));
        $this->assertSame(PurchaseMode::Quote, $product->purchaseModeFor(4));
    }

    public function test_bajo_pedido_siempre_cotiza(): void
    {
        $this->assertSame(PurchaseMode::Quote, $this->product(['is_on_demand' => true])->purchaseModeFor(1));
    }

    public function test_cotiza_cuando_la_cantidad_excede_la_existencia(): void
    {
        $product = $this->product(['stock_qty' => 4]);

        $this->assertSame(PurchaseMode::Buy, $product->purchaseModeFor(4));
        $this->assertSame(PurchaseMode::Quote, $product->purchaseModeFor(5));
    }

    public function test_un_equipo_de_renta_nunca_se_cobra_en_linea(): void
    {
        $product = $this->product(['is_for_sale' => false, 'is_rental' => true]);

        $this->assertSame(PurchaseMode::Quote, $product->purchaseModeFor(1));
    }

    public function test_rechaza_cantidades_no_positivas(): void
    {
        $this->assertSame(PurchaseMode::Quote, $this->product()->purchaseModeFor(0));
        $this->assertSame(PurchaseMode::Quote, $this->product()->purchaseModeFor(-3));
    }

    public function test_etiqueta_de_existencia(): void
    {
        $this->assertSame('Bajo pedido', $this->product(['is_on_demand' => true])->stockLabel());
        $this->assertSame('Sin existencia', $this->product(['stock_qty' => 0])->stockLabel());
        $this->assertSame('Últimas piezas', $this->product(['stock_qty' => 3, 'low_stock_threshold' => 5])->stockLabel());
        $this->assertSame('Disponible', $this->product(['stock_qty' => 40, 'low_stock_threshold' => 5])->stockLabel());
    }
}
