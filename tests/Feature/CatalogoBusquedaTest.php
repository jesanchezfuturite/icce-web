<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Motor de búsqueda y filtros del catálogo (fase 3).
 * Se arma un catálogo mínimo y controlado en vez de sembrar los 173 reales:
 * las aserciones deben depender de la regla, no del contenido del cliente.
 */
class CatalogoBusquedaTest extends TestCase
{
    use RefreshDatabase;

    private Category $herramientas;

    private Category $llanas;

    protected function setUp(): void
    {
        parent::setUp();

        $kraft = Brand::create(['name' => 'Kraft Tool', 'slug' => 'kraft-tool']);
        $somero = Brand::create(['name' => 'Somero Enterprises', 'slug' => 'somero-enterprises']);

        $this->herramientas = Category::create(['name' => 'Herramientas', 'slug' => 'herramientas']);
        $this->llanas = Category::create([
            'name' => 'Llanas', 'slug' => 'llanas', 'parent_id' => $this->herramientas->id,
        ]);
        $materiales = Category::create(['name' => 'Materiales', 'slug' => 'materiales']);

        $this->product('Llana de acabado 16x4', 'LL-001', $this->llanas, $kraft, stock: 50);
        $this->product('Llana pecho paloma 6', 'LL-002', $this->llanas, $kraft, stock: 3);
        $this->product('Regla láser S-940', 'RL-940', $materiales, $somero, stock: 0, onDemand: true);
        $this->product('Sellador de juntas', 'SJ-100', $materiales, null, stock: 0);
    }

    private function product(
        string $name,
        string $sku,
        Category $category,
        ?Brand $brand,
        int $stock,
        bool $onDemand = false,
    ): Product {
        return Product::create([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'sku' => $sku,
            'category_id' => $category->id,
            'brand_id' => $brand?->id,
            'price' => 1000,
            'stock_qty' => $stock,
            'is_on_demand' => $onDemand,
            'is_active' => true,
            'is_for_sale' => true,
        ]);
    }

    public function test_sin_filtros_lista_todo_el_catalogo(): void
    {
        $component = Livewire::test('catalogo.explorador')
            ->assertSee('Llana de acabado 16x4')
            ->assertSee('Regla láser S-940');

        $this->assertSame(4, $component->instance()->results()->total());
    }

    public function test_busca_por_nombre(): void
    {
        Livewire::test('catalogo.explorador')
            ->set('search', 'pecho paloma')
            ->assertSee('Llana pecho paloma 6')
            ->assertDontSee('Regla láser S-940');
    }

    public function test_busca_por_sku_exacto(): void
    {
        Livewire::test('catalogo.explorador')
            ->set('search', 'RL-940')
            ->assertSee('Regla láser S-940')
            ->assertDontSee('Llana de acabado');
    }

    public function test_busca_por_marca(): void
    {
        Livewire::test('catalogo.explorador')
            ->set('search', 'Somero')
            ->assertSee('Regla láser S-940')
            ->assertDontSee('Llana de acabado');
    }

    public function test_cada_palabra_debe_coincidir(): void
    {
        // "llana somero" no debe traer ni todas las llanas ni todo lo de Somero
        Livewire::test('catalogo.explorador')
            ->set('search', 'llana somero')
            ->assertDontSee('Llana de acabado 16x4')
            ->assertDontSee('Regla láser S-940');
    }

    public function test_filtra_por_disponibilidad(): void
    {
        Livewire::test('catalogo.explorador')
            ->set('availability', 'bajo-pedido')
            ->assertSee('Regla láser S-940')
            ->assertDontSee('Llana de acabado 16x4');

        Livewire::test('catalogo.explorador')
            ->set('availability', 'disponible')
            ->assertSee('Llana de acabado 16x4')
            ->assertDontSee('Regla láser S-940');

        Livewire::test('catalogo.explorador')
            ->set('availability', 'agotado')
            ->assertSee('Sellador de juntas')
            ->assertDontSee('Llana de acabado 16x4');
    }

    public function test_filtra_por_marca(): void
    {
        Livewire::test('catalogo.explorador')
            ->set('brandSlugs', ['somero-enterprises'])
            ->assertSee('Regla láser S-940')
            ->assertDontSee('Llana de acabado 16x4');
    }

    public function test_la_categoria_de_la_ruta_acota_los_resultados(): void
    {
        Livewire::test('catalogo.explorador', ['category' => $this->herramientas])
            ->assertSee('Llana de acabado 16x4')
            ->assertDontSee('Regla láser S-940');
    }

    public function test_la_rama_incluye_a_los_descendientes(): void
    {
        // Los productos cuelgan de "Llanas", hija de "Herramientas"
        Livewire::test('catalogo.explorador', ['category' => $this->herramientas])
            ->assertSee('Llana pecho paloma 6');
    }

    public function test_ordena_por_precio(): void
    {
        Product::where('sku', 'LL-001')->update(['price' => 50]);
        Product::where('sku', 'LL-002')->update(['price' => 9000]);

        $ascendente = Livewire::test('catalogo.explorador')
            ->set('search', 'llana')
            ->set('sort', 'precio-asc')
            ->instance()
            ->results()
            ->pluck('sku')
            ->all();

        $this->assertSame(['LL-001', 'LL-002'], $ascendente);

        $descendente = Livewire::test('catalogo.explorador')
            ->set('search', 'llana')
            ->set('sort', 'precio-desc')
            ->instance()
            ->results()
            ->pluck('sku')
            ->all();

        $this->assertSame(['LL-002', 'LL-001'], $descendente);
    }

    public function test_limpiar_filtros_restablece_todo(): void
    {
        Livewire::test('catalogo.explorador')
            ->set('search', 'llana')
            ->set('availability', 'disponible')
            ->set('brandSlugs', ['kraft-tool'])
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('availability', '')
            ->assertSet('brandSlugs', [])
            ->assertSee('Regla láser S-940');
    }

    public function test_los_filtros_viajan_en_la_url(): void
    {
        $this->get('/catalogo?q=llana&disp=disponible')
            ->assertOk()
            ->assertSee('Llana de acabado 16x4')
            ->assertDontSee('Regla láser S-940');
    }

    public function test_una_busqueda_sin_resultados_no_revienta(): void
    {
        $this->get('/catalogo?q=zzzznoexiste')
            ->assertOk()
            ->assertSee('Sin resultados');
    }
}
