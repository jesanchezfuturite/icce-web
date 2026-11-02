<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\ColumnLength;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Un texto más largo que su columna no debe llegar a la base: reventaba el
 * guardado con un error 500 («Data too long for column 'meta_description'»).
 * Ahora cada campo de texto del backoffice se limita al largo de su columna.
 *
 * SQLite (las pruebas) no declara largos, así que se simulan los de MariaDB.
 */
class LimitesDeTextoEnElAdminTest extends TestCase
{
    use RefreshDatabase;

    private Product $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->largosDeMariaDb();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]));

        $categoria = Category::create(['name' => 'Selladores', 'slug' => 'selladores']);
        $this->producto = Product::create([
            'name' => 'Pourthane NS', 'slug' => 'pourthane-ns', 'sku' => 'PNS-1',
            'category_id' => $categoria->id, 'price' => 100, 'stock_qty' => 5, 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        ColumnLength::flush();

        parent::tearDown();
    }

    /** Siembra la memoria de ColumnLength con los largos reales de la base de producción. */
    private function largosDeMariaDb(): void
    {
        $cache = new \ReflectionProperty(ColumnLength::class, 'cache');
        $cache->setValue(null, [
            'products' => [
                'sku' => 50, 'name' => 200, 'slug' => 200, 'short_description' => 255, 'description' => 65535,
                'unit' => 20, 'meta_title' => 191, 'meta_description' => 255,
            ],
            'categories' => ['name' => 150, 'slug' => 150, 'meta_title' => 191, 'meta_description' => 255],
        ]);
    }

    // ---------------- Lectura de tipos ----------------

    public function test_se_lee_el_largo_de_los_tipos_de_texto(): void
    {
        $this->assertSame(255, ColumnLength::parse('varchar(255)'));
        $this->assertSame(50, ColumnLength::parse('VARCHAR(50)'));
        $this->assertSame(3, ColumnLength::parse('char(3)'));
        $this->assertSame(65535, ColumnLength::parse('text'));
        $this->assertSame(255, ColumnLength::parse('tinytext'));
    }

    public function test_los_tipos_que_no_son_de_texto_corto_no_llevan_limite(): void
    {
        foreach (['json', 'int', 'bigint unsigned', 'decimal(10,2)', 'datetime', 'tinyint(1)', 'longtext', 'varchar'] as $tipo) {
            $this->assertNull(ColumnLength::parse($tipo), $tipo);
        }
    }

    // ---------------- El caso que falló en el sitio publicado ----------------

    public function test_una_descripcion_para_buscadores_de_440_caracteres_se_rechaza_en_el_formulario(): void
    {
        $larga = str_repeat('Sellador de poliuretano elástico. ', 14);   // ~470 caracteres

        Livewire::test(EditProduct::class, ['record' => $this->producto->getRouteKey()])
            ->fillForm(['meta_description' => $larga])
            ->call('save')
            ->assertHasFormErrors(['meta_description']);

        $this->assertNull($this->producto->fresh()->meta_description);
    }

    public function test_una_descripcion_de_exactamente_255_caracteres_si_se_guarda(): void
    {
        $justa = str_repeat('a', 255);

        Livewire::test(EditProduct::class, ['record' => $this->producto->getRouteKey()])
            ->fillForm(['meta_description' => $justa])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($justa, $this->producto->fresh()->meta_description);
    }

    public function test_el_mensaje_dice_el_limite_en_espanol(): void
    {
        Livewire::test(EditProduct::class, ['record' => $this->producto->getRouteKey()])
            ->fillForm(['meta_description' => str_repeat('a', 300)])
            ->call('save')
            ->assertHasFormErrors(['meta_description' => 'max']);

        $this->assertStringContainsString('255', __('validation.max.string', ['attribute' => 'x', 'max' => 255]));
    }

    // ---------------- Otros campos y otros formularios ----------------

    public function test_los_demas_campos_del_producto_respetan_su_columna(): void
    {
        foreach ([
            'name' => 201, 'slug' => 201, 'sku' => 51, 'unit' => 21, 'short_description' => 256, 'meta_title' => 192,
        ] as $campo => $largo) {
            Livewire::test(EditProduct::class, ['record' => $this->producto->getRouteKey()])
                ->fillForm([$campo => str_repeat('x', $largo)])
                ->call('save')
                ->assertHasFormErrors([$campo]);
        }
    }

    public function test_el_limite_aplica_tambien_a_otros_formularios_como_categorias(): void
    {
        $categoria = Category::create(['name' => 'Llanas', 'slug' => 'llanas']);

        Livewire::test(EditCategory::class, ['record' => $categoria->getRouteKey()])
            ->fillForm(['meta_description' => str_repeat('a', 256)])
            ->call('save')
            ->assertHasFormErrors(['meta_description']);
    }

    public function test_un_campo_largo_como_la_descripcion_del_producto_no_se_recorta(): void
    {
        $texto = str_repeat('Texto largo de la descripción. ', 100);   // ~3,100 caracteres

        Livewire::test(EditProduct::class, ['record' => $this->producto->getRouteKey()])
            ->fillForm(['description' => $texto])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($texto, $this->producto->fresh()->description);
    }

    public function test_un_formulario_sin_modelo_no_recibe_limite(): void
    {
        $this->assertNull(ColumnLength::for('tabla_que_no_existe', 'x'));
    }
}
