<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\UrlRedirect;
use Database\Seeders\BrandSeeder;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CmsSeeder;
use Database\Seeders\UrlRedirectSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Blindaje de la migración de dominio (TRD 4.3 / RNF-03). Es la parte del
 * proyecto donde un error no se ve el día uno, sino tres meses después en el
 * tráfico orgánico.
 */
class MigracionSeoTest extends TestCase
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
            UrlRedirectSeeder::class,
        ]);
    }

    // ---------------- Redirecciones ----------------

    public function test_una_ruta_vieja_redirige_permanentemente(): void
    {
        $this->get('/Llanas-Herramientas-Para-Concreto.html')
            ->assertStatus(301)
            ->assertRedirect('/catalogo/llanas');
    }

    public function test_la_portada_vieja_lleva_a_la_nueva(): void
    {
        $this->get('/index.html')->assertStatus(301)->assertRedirect('/');
    }

    public function test_una_ficha_de_producto_conserva_su_destino(): void
    {
        $redirect = UrlRedirect::whereNotNull('new_path')
            ->where('new_path', 'like', '/producto/%')
            ->firstOrFail();

        $this->get($redirect->old_path)
            ->assertStatus(301)
            ->assertRedirect($redirect->new_path);
    }

    public function test_las_paginas_secuestradas_se_retiran_con_410(): void
    {
        // 410 y no 301: redirigirlas trasladaría a la estructura nueva la señal
        // de spam que el buscador ya asoció a esas rutas.
        foreach (['/TiltUp.html', '/Desbaste-y-Abrillantado-Metales.html'] as $ruta) {
            $this->get($ruta)->assertStatus(410);
        }
    }

    public function test_las_plantillas_muertas_del_tema_tambien_son_410(): void
    {
        $this->get('/page-faq.html')->assertStatus(410);
        $this->get('/checkout.html')->assertStatus(410);
    }

    public function test_una_ruta_inexistente_sigue_siendo_404(): void
    {
        $this->get('/esto-nunca-existio.html')->assertNotFound();
    }

    public function test_una_redireccion_inactiva_no_se_ejecuta(): void
    {
        // Las 27 rutas sin mapear se sembraron inactivas justamente para que
        // no redirijan a ningún lado hasta que alguien decida su destino.
        $pendiente = UrlRedirect::where('is_active', false)->firstOrFail();

        $this->get($pendiente->old_path)->assertNotFound();
    }

    public function test_se_registra_el_trafico_de_cada_ruta_vieja(): void
    {
        $redirect = UrlRedirect::where('old_path', '/index.html')->firstOrFail();
        $this->assertSame(0, $redirect->hits);

        $this->get('/index.html');
        $this->get('/index.html');

        $redirect->refresh();
        $this->assertSame(2, $redirect->hits);
        $this->assertNotNull($redirect->last_hit_at);
    }

    public function test_ninguna_redireccion_activa_apunta_al_vacio(): void
    {
        $rotas = UrlRedirect::where('is_active', true)
            ->where('status_code', 301)
            ->whereNull('new_path')
            ->count();

        $this->assertSame(0, $rotas, 'Una redirección 301 activa sin destino produce un error en producción.');
    }

    // ---------------- Sitemap y robots ----------------

    public function test_el_sitemap_responde_como_xml(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<urlset', escape: false);
    }

    public function test_el_sitemap_incluye_catalogo_y_contenido(): void
    {
        $product = Product::active()->forSale()->where('is_rental', false)->firstOrFail();
        $category = Category::active()->firstOrFail();

        $this->get('/sitemap.xml')
            ->assertSee(url("/producto/{$product->slug}"), escape: false)
            ->assertSee(url("/catalogo/{$category->slug}"), escape: false)
            ->assertSee(url('/descargas'), escape: false);
    }

    public function test_los_equipos_de_renta_van_bajo_su_propia_ruta(): void
    {
        $rental = Product::active()->rentals()->firstOrFail();

        $response = $this->get('/sitemap.xml');
        $response->assertSee(url("/renta/{$rental->slug}"), escape: false);
        $response->assertDontSee(url("/producto/{$rental->slug}"), escape: false);
    }

    public function test_el_sitemap_no_expone_zonas_privadas(): void
    {
        $response = $this->get('/sitemap.xml');

        foreach (['/admin', '/portal', '/carrito', '/checkout', '/ingresar'] as $privada) {
            $response->assertDontSee('<loc>'.url($privada).'</loc>', escape: false);
        }
    }

    // ---------------- Datos estructurados ----------------

    public function test_el_home_declara_la_organizacion(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('"@type":"Organization"', $html);
        $this->assertStringContainsString('"@type":"WebSite"', $html);
    }

    public function test_la_ficha_declara_el_producto_con_su_precio(): void
    {
        $product = Product::active()->forSale()->where('is_rental', false)->where('price', '>', 0)->firstOrFail();

        $html = $this->get("/producto/{$product->slug}")->getContent();

        $this->assertStringContainsString('"@type":"Product"', $html);
        $this->assertStringContainsString('"sku":"'.$product->sku.'"', $html);
        $this->assertStringContainsString('"priceCurrency":"MXN"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
    }

    public function test_el_json_ld_es_json_valido(): void
    {
        $product = Product::active()->forSale()->where('is_rental', false)->firstOrFail();
        $html = $this->get("/producto/{$product->slug}")->getContent();

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        $this->assertGreaterThanOrEqual(3, count($matches[1]));

        foreach ($matches[1] as $bloque) {
            json_decode(trim($bloque), true);
            $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'Un bloque JSON-LD quedó malformado.');
        }
    }
}
