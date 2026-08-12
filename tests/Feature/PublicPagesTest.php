<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use Database\Seeders\BrandSeeder;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CmsSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Humo de las páginas públicas de la fase 2. Con preventLazyLoading activo,
 * cualquier relación no precargada revienta la prueba en vez de degradar el
 * rendimiento en silencio.
 */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            UserSeeder::class,
            BrandSeeder::class,
            CategorySeeder::class,
            CatalogSeeder::class,
            CmsSeeder::class,
        ]);
    }

    public static function staticRoutes(): array
    {
        return [
            'home' => ['/'],
            'empresa' => ['/empresa'],
            'servicios' => ['/servicios'],
            'contacto' => ['/contacto'],
            'aviso de privacidad' => ['/aviso-de-privacidad'],
            'políticas' => ['/politicas'],
            'catálogo' => ['/catalogo'],
            'renta' => ['/renta'],
            'requisitos de renta' => ['/renta/requisitos'],
            'proyectos' => ['/proyectos'],
            'blog' => ['/blog'],
            'centro de descargas' => ['/descargas'],
            'marcas' => ['/marcas'],
        ];
    }

    #[DataProvider('staticRoutes')]
    public function test_las_paginas_estaticas_responden(string $path): void
    {
        $this->get($path)->assertOk();
    }

    public function test_la_ficha_de_producto_responde(): void
    {
        $product = Product::active()->forSale()->firstOrFail();

        $this->get("/producto/{$product->slug}")
            ->assertOk()
            ->assertSee($product->name);
    }

    public function test_la_ficha_de_equipo_en_renta_responde(): void
    {
        $product = Product::active()->rentals()->firstOrFail();

        $this->get("/renta/{$product->slug}")->assertOk();
    }

    public function test_cada_producto_vive_en_una_sola_ruta(): void
    {
        // Las rutas están separadas a propósito: /producto es para venta y
        // /renta para captación de lead, con llamados a la acción distintos.
        // Servir el mismo producto en ambas crearía contenido duplicado.
        $rental = Product::active()->rentals()->firstOrFail();
        $sale = Product::active()->forSale()->where('is_rental', false)->firstOrFail();

        $this->get("/renta/{$sale->slug}")->assertNotFound();
        $this->get("/producto/{$rental->slug}")->assertNotFound();

        $this->get("/renta/{$rental->slug}")->assertOk();
        $this->get("/producto/{$sale->slug}")->assertOk();
    }

    public function test_las_categorias_listan_productos_de_su_descendencia(): void
    {
        $root = Category::where('slug', 'herramientas-para-concreto')->firstOrFail();

        $this->get("/catalogo/{$root->slug}")
            ->assertOk()
            ->assertSee('productos');

        $this->assertGreaterThan(0, $root->totalProducts());
    }

    public function test_el_articulo_y_el_proyecto_responden(): void
    {
        $post = Post::published()->firstOrFail();
        $project = Project::firstOrFail();

        $this->get("/blog/{$post->slug}")->assertOk()->assertSee($post->title);
        $this->get("/proyectos/{$project->slug}")->assertOk();
    }

    public function test_un_borrador_no_es_visible(): void
    {
        $draft = Post::published()->firstOrFail();
        $draft->update(['published_at' => now()->addWeek()]);

        $this->get("/blog/{$draft->slug}")->assertNotFound();
    }

    public function test_la_pagina_de_marca_responde(): void
    {
        $brand = Brand::whereHas('products')->firstOrFail();

        $this->get("/marcas/{$brand->slug}")->assertOk()->assertSee($brand->name);
    }

    public function test_lo_inexistente_da_404(): void
    {
        $this->get('/producto/no-existe')->assertNotFound();
        $this->get('/catalogo/no-existe')->assertNotFound();
        $this->get('/blog/no-existe')->assertNotFound();
    }
}
