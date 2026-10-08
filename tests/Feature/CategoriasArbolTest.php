<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Árbol de categorías de hasta cuatro niveles: selector del admin, menú, migas
 * de pan, caché del menú y qué pasa con los productos al borrar una categoría.
 * Catálogo mínimo y controlado.
 *
 * El ejemplo es el de la clienta: Servicios › Laboratorio › Evaluación › Números F.
 */
class CategoriasArbolTest extends TestCase
{
    use RefreshDatabase;

    private Category $servicios;

    private Category $laboratorio;

    private Category $evaluacion;

    private Category $numerosF;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servicios = Category::create(['name' => 'Servicios', 'slug' => 'servicios']);
        $this->laboratorio = Category::create([
            'name' => 'Laboratorio', 'slug' => 'laboratorio', 'parent_id' => $this->servicios->id,
        ]);
        $this->evaluacion = Category::create([
            'name' => 'Evaluacion de regularidad', 'slug' => 'evaluacion', 'parent_id' => $this->laboratorio->id,
        ]);
        $this->numerosF = Category::create([
            'name' => 'Numeros F', 'slug' => 'numeros-f', 'parent_id' => $this->evaluacion->id,
        ]);
    }

    private function producto(string $nombre, ?Category $categoria): Product
    {
        return Product::create([
            'name' => $nombre, 'slug' => str($nombre)->slug()->value(), 'sku' => 'T-'.str($nombre)->slug()->upper()->value(),
            'category_id' => $categoria?->id, 'price' => 500, 'stock_qty' => 10,
            'is_active' => true, 'is_for_sale' => true,
        ]);
    }

    private function migas(string $url): string
    {
        $html = $this->get($url)->assertOk()->getContent();

        preg_match('/<nav aria-label="Ruta de navegación".*?<\/nav>/s', $html, $coincidencia);

        return $coincidencia[0] ?? '';
    }

    public function test_los_ancestros_se_devuelven_de_la_principal_hacia_abajo(): void
    {
        $this->assertSame(
            ['Servicios', 'Laboratorio', 'Evaluacion de regularidad'],
            $this->numerosF->ancestors()->pluck('name')->all(),
        );
        $this->assertSame([], $this->servicios->ancestors()->pluck('name')->all());
    }

    public function test_el_selector_de_padre_ofrece_hasta_el_tercer_nivel_y_no_un_quinto(): void
    {
        $opciones = Category::parentOptionsFor(null);

        $this->assertArrayHasKey($this->servicios->id, $opciones);
        $this->assertArrayHasKey($this->laboratorio->id, $opciones);
        $this->assertArrayHasKey($this->evaluacion->id, $opciones);
        $this->assertArrayNotHasKey($this->numerosF->id, $opciones);
        $this->assertSame('Servicios › Laboratorio › Evaluacion de regularidad', $opciones[$this->evaluacion->id]);
    }

    public function test_una_categoria_no_puede_colgar_de_si_misma_ni_de_sus_descendientes(): void
    {
        $this->assertSame([], Category::parentOptionsFor($this->servicios->fresh()));
    }

    public function test_una_categoria_con_hijas_no_puede_bajar_a_un_nivel_que_se_pase_del_tope(): void
    {
        $otros = Category::create(['name' => 'Otros', 'slug' => 'otros']);
        $varios = Category::create(['name' => 'Varios', 'slug' => 'varios', 'parent_id' => $otros->id]);

        // Laboratorio arrastra dos niveles debajo: bajo una principal cabe (1+1+2=4),
        // bajo una subcategoría ya no (2+1+2=5).
        $opciones = Category::parentOptionsFor($this->laboratorio->fresh());

        $this->assertArrayHasKey($otros->id, $opciones);
        $this->assertArrayNotHasKey($varios->id, $opciones);
    }

    public function test_se_puede_mover_una_categoria_a_otro_lugar_del_arbol(): void
    {
        $otros = Category::create(['name' => 'Otros', 'slug' => 'otros']);

        $this->evaluacion->update(['parent_id' => $otros->id]);

        $this->assertSame(['Otros'], $this->evaluacion->fresh()->ancestors()->pluck('name')->all());
        $this->assertSame(
            ['Otros', 'Evaluacion de regularidad'],
            $this->numerosF->fresh()->ancestors()->pluck('name')->all(),
        );
    }

    public function test_el_menu_del_catalogo_muestra_hasta_el_tercer_nivel(): void
    {
        $this->get('/contacto')
            ->assertOk()
            ->assertSee('Evaluacion de regularidad')
            ->assertSee('/catalogo/evaluacion', false);
    }

    public function test_crear_una_categoria_actualiza_el_menu_sin_esperar_al_cache(): void
    {
        $this->get('/contacto')->assertDontSee('Impermeabilizantes');

        Category::create(['name' => 'Impermeabilizantes', 'slug' => 'impermeabilizantes']);

        $this->get('/contacto')->assertSee('Impermeabilizantes');
    }

    public function test_las_migas_de_pan_de_una_categoria_de_cuarto_nivel_incluyen_toda_la_cadena(): void
    {
        $migas = $this->migas('/catalogo/numeros-f');

        $this->assertMatchesRegularExpression(
            '/Catálogo.*Servicios.*Laboratorio.*Evaluacion de regularidad.*Numeros F/s',
            $migas,
        );
    }

    public function test_la_ficha_de_un_producto_de_cuarto_nivel_responde_con_la_cadena_completa(): void
    {
        $this->producto('Medicion de numeros F', $this->numerosF);

        $migas = $this->migas('/producto/medicion-de-numeros-f');

        $this->assertMatchesRegularExpression('/Servicios.*Laboratorio.*Evaluacion de regularidad.*Numeros F.*Medicion/s', $migas);
    }

    public function test_al_borrar_una_categoria_sus_productos_se_conservan_sin_categoria(): void
    {
        $producto = $this->producto('Medicion de numeros F', $this->numerosF);

        $this->numerosF->delete();

        $this->assertNotNull($producto->fresh());
        $this->assertNull($producto->fresh()->category_id);
    }

    public function test_un_producto_sin_categoria_se_ve_en_el_catalogo_y_su_ficha_responde(): void
    {
        $this->producto('Producto suelto', null);

        $this->get('/catalogo')->assertOk()->assertSee('Producto suelto');
        $this->get('/producto/producto-suelto')->assertOk()->assertSee('Producto suelto');
    }
}
