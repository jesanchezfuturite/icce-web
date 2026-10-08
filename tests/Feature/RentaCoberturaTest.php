<?php

namespace Tests\Feature;

use App\Enums\RentalCoverage;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cobertura, operador y requisitos de renta tal como los confirmó el cliente.
 * La cobertura y si el equipo incluye operador son datos de cada producto, que
 * se editan desde Inventario.
 */
class RentaCoberturaTest extends TestCase
{
    use RefreshDatabase;

    private Category $reglas;

    private Brand $somero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->somero = Brand::create(['name' => 'Somero Enterprises', 'slug' => 'somero-enterprises']);
        $this->reglas = Category::create(['name' => 'Reglas Láser', 'slug' => 'reglas-laser']);
    }

    private function equipo(
        string $nombre,
        RentalCoverage $cobertura,
        bool $operador = false,
        bool $conCategoria = true,
    ): Product {
        return Product::create([
            'name' => $nombre,
            'slug' => str($nombre)->slug()->value(),
            'sku' => 'R-'.str($nombre)->slug()->upper()->value(),
            'category_id' => $conCategoria ? $this->reglas->id : null,
            'brand_id' => $this->somero->id,
            'price' => 0,
            'stock_qty' => 0,
            'is_rental' => true,
            'is_for_sale' => false,
            'is_active' => true,
            'rental_coverage' => $cobertura,
            'rental_includes_operator' => $operador,
        ]);
    }

    public function test_un_equipo_nacional_con_operador_lo_dice_en_su_ficha(): void
    {
        $this->equipo('Regla Laser S240', RentalCoverage::National, operador: true);

        $this->get('/renta/regla-laser-s240')
            ->assertOk()
            ->assertSee('Cobertura en toda la República')
            ->assertSee('Operador incluido')
            ->assertDontSee('Solo cobertura local (Monterrey)');
    }

    public function test_un_equipo_local_dice_solo_monterrey_y_no_trae_operador(): void
    {
        $this->equipo('Placa Vibratoria', RentalCoverage::Local);

        $this->get('/renta/placa-vibratoria')
            ->assertOk()
            ->assertSee('Solo cobertura local (Monterrey)')
            ->assertSee('sin aditamentos')
            ->assertDontSee('Operador incluido')
            ->assertDontSee('Cobertura en toda la República');
    }

    public function test_el_operador_incluido_depende_del_producto_y_no_de_la_cobertura(): void
    {
        $this->equipo('Equipo nacional sin operador', RentalCoverage::National, operador: false);
        $this->equipo('Equipo local con operador', RentalCoverage::Local, operador: true);

        $this->get('/renta/equipo-nacional-sin-operador')
            ->assertOk()
            ->assertSee('Cobertura en toda la República')
            ->assertDontSee('Operador incluido');

        $this->get('/renta/equipo-local-con-operador')
            ->assertOk()
            ->assertSee('Solo cobertura local (Monterrey)')
            ->assertSee('Operador incluido');
    }

    public function test_la_ficha_de_un_equipo_con_hermanos_en_su_categoria_muestra_sus_relacionados(): void
    {
        $this->equipo('Regla Laser S240', RentalCoverage::National);
        $this->equipo('Regla Laser S940', RentalCoverage::National);

        $this->get('/renta/regla-laser-s240')
            ->assertOk()
            ->assertSee('Otros equipos')
            ->assertSee('Regla Laser S940')
            ->assertSee('Somero Enterprises');
    }

    public function test_un_equipo_sin_categoria_tiene_ficha_y_aparece_en_el_indice_de_renta(): void
    {
        $this->equipo('Generador suelto', RentalCoverage::Local, conCategoria: false);

        $this->get('/renta/generador-suelto')->assertOk()->assertSee('Generador suelto');
        $this->get('/renta')->assertOk()->assertSee('Generador suelto');
    }

    public function test_el_formulario_no_pregunta_por_operador_en_un_equipo_nacional(): void
    {
        $nacional = $this->equipo('Regla Laser S240', RentalCoverage::National, operador: true);

        Livewire::test('renta.solicitud')
            ->set('productId', $nacional->id)
            ->assertSee('Para obra fuera de Monterrey')
            ->assertDontSee('Necesito operador capacitado');
    }

    public function test_el_formulario_pregunta_por_operador_en_un_equipo_local_que_no_lo_incluye(): void
    {
        $local = $this->equipo('Placa Vibratoria', RentalCoverage::Local);

        Livewire::test('renta.solicitud')
            ->set('productId', $local->id)
            ->assertSee('Necesito operador capacitado');
    }

    public function test_el_formulario_no_pregunta_por_operador_si_el_equipo_ya_lo_incluye(): void
    {
        $local = $this->equipo('Equipo local con operador', RentalCoverage::Local, operador: true);

        Livewire::test('renta.solicitud')
            ->set('productId', $local->id)
            ->assertDontSee('Necesito operador capacitado');
    }

    public function test_los_requisitos_de_renta_son_los_que_confirmo_el_cliente(): void
    {
        $this->get('/renta/requisitos')
            ->assertOk()
            ->assertSee('Acta constitutiva')
            ->assertSee('Copia del INE del representante legal')
            ->assertSee('Constancia de situación fiscal')
            ->assertSee('Comprobante de domicilio')
            ->assertSee('Cheque de garantía por el monto del equipo')
            ->assertSee('INE de la persona que firma los cheques')
            ->assertDontSee('Condiciones generales')
            ->assertDontSee('Depósito en garantía según');
    }
}
