<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * En el inventario del backoffice la categoría de un producto se cambia desde
 * el propio renglón, sin abrir la ficha.
 */
class CategoriaEnLineaTest extends TestCase
{
    use RefreshDatabase;

    private Category $llanas;

    private Category $selladores;

    private Category $hija;

    private Product $producto;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->llanas = Category::create(['name' => 'Llanas', 'slug' => 'llanas']);
        $this->selladores = Category::create(['name' => 'Selladores', 'slug' => 'selladores']);
        $this->hija = Category::create(['name' => 'Epóxicos', 'slug' => 'epoxicos', 'parent_id' => $this->selladores->id]);

        $this->producto = Product::create([
            'name' => 'Llana de acabado', 'slug' => 'llana', 'sku' => 'LL-001',
            'category_id' => $this->llanas->id, 'price' => 1000, 'stock_qty' => 5, 'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]));
    }

    private function cambiar(mixed $categoria): void
    {
        Livewire::test(ListProducts::class)
            ->call('updateTableColumnState', 'category_id', (string) $this->producto->getKey(), $categoria)
            ->assertHasNoErrors();
    }

    public function test_el_renglon_cambia_la_categoria_del_producto(): void
    {
        $this->cambiar($this->selladores->id);

        $this->assertSame($this->selladores->id, $this->producto->fresh()->category_id);
    }

    public function test_se_puede_mover_a_una_subcategoria_de_cualquier_nivel(): void
    {
        $this->cambiar($this->hija->id);

        $this->assertSame($this->hija->id, $this->producto->fresh()->category_id);
    }

    public function test_se_puede_dejar_el_producto_sin_categoria_sin_borrarlo(): void
    {
        $this->cambiar(null);

        $producto = $this->producto->fresh();
        $this->assertNotNull($producto);
        $this->assertNull($producto->category_id);
    }

    public function test_no_acepta_una_categoria_que_no_existe(): void
    {
        Livewire::test(ListProducts::class)
            ->call('updateTableColumnState', 'category_id', (string) $this->producto->getKey(), 99999);

        $this->assertSame($this->llanas->id, $this->producto->fresh()->category_id);
    }

    public function test_las_opciones_traen_la_ruta_completa_y_estan_ordenadas(): void
    {
        $opciones = Category::optionsWithPath();

        $this->assertSame('Selladores › Epóxicos', $opciones[$this->hija->id]);
        $this->assertSame(
            ['Llanas', 'Selladores', 'Selladores › Epóxicos'],
            array_values($opciones),
        );
    }

    public function test_el_listado_de_inventario_carga_con_el_selector(): void
    {
        Livewire::test(ListProducts::class)->assertSuccessful()->assertCanSeeTableRecords([$this->producto]);
    }
}
