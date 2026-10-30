<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Rules\OptimizableImage;
use App\Support\ProductImageOptimizer;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Imágenes de producto subidas desde el backoffice (WebP, 400 KB máximo, se
 * reducen solas), fichas técnicas en PDF y especificaciones como pares
 * característica → valor.
 *
 * El disco `site` apunta a public/: en pruebas se redirige a una carpeta
 * temporal para no escribir archivos en el sitio real.
 */
class ImagenesYFichasDeProductoTest extends TestCase
{
    use RefreshDatabase;

    private string $raiz;

    private Product $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->raiz = storage_path('framework/testing/site-'.uniqid());
        File::ensureDirectoryExists($this->raiz);
        config(['filesystems.disks.site.root' => $this->raiz]);
        Storage::forgetDisk('site');

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]));

        $categoria = Category::create(['name' => 'Selladores', 'slug' => 'selladores']);
        $this->producto = Product::create([
            'name' => 'Pourthane NS', 'slug' => 'pourthane-ns', 'sku' => 'PNS-1',
            'category_id' => $categoria->id, 'price' => 3269.90, 'stock_qty' => 76, 'is_active' => true,
            'specs' => ['imagen_origen' => 'PNS.jpg', 'Color' => 'Gris'],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->raiz);
        Storage::forgetDisk('site');

        parent::tearDown();
    }

    /** Imagen con ruido (no comprime bien) del tamaño pedido, en PNG. */
    private function imagenPesada(int $ancho = 2400, int $alto = 1800): string
    {
        $img = imagecreatetruecolor($ancho, $alto);

        for ($i = 0; $i < 60000; $i++) {
            imagesetpixel($img, random_int(0, $ancho - 1), random_int(0, $alto - 1), random_int(0, 0xFFFFFF));
        }

        // Rayas de colores para que no sea un fondo plano
        for ($y = 0; $y < $alto; $y += 7) {
            imageline($img, 0, $y, $ancho, ($y * 3) % $alto, imagecolorallocate($img, $y % 255, ($y * 2) % 255, ($y * 5) % 255));
        }

        ob_start();
        imagepng($img, null, 0);   // sin compresión: pesa varios MB

        return (string) ob_get_clean();
    }

    private function archivo(string $bytes, string $nombre): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, $bytes);
    }

    // ---------------- Optimizador ----------------

    public function test_una_imagen_pesada_se_reduce_a_webp_de_400_kb_o_menos(): void
    {
        $original = $this->imagenPesada();
        $this->assertGreaterThan(2 * 1024 * 1024, strlen($original));

        $resultado = ProductImageOptimizer::optimize($original);

        $this->assertLessThanOrEqual(400 * 1024, strlen($resultado));
        $info = getimagesizefromstring($resultado);
        $this->assertSame(IMAGETYPE_WEBP, $info[2]);
        $this->assertLessThanOrEqual(1600, max($info[0], $info[1]));
    }

    public function test_un_webp_que_ya_cumple_se_guarda_sin_recomprimirlo(): void
    {
        $img = imagecreatetruecolor(800, 600);
        imagefill($img, 0, 0, imagecolorallocate($img, 120, 160, 30));
        ob_start();
        imagewebp($img, null, 80);
        $webp = (string) ob_get_clean();

        $this->assertSame($webp, ProductImageOptimizer::optimize($webp));
    }

    public function test_conserva_la_proporcion_al_reducir(): void
    {
        $resultado = ProductImageOptimizer::optimize($this->imagenPesada(3000, 1500));
        [$ancho, $alto] = getimagesizefromstring($resultado);

        $this->assertEqualsWithDelta(2.0, $ancho / $alto, 0.02);
    }

    public function test_un_archivo_que_no_es_imagen_se_rechaza(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProductImageOptimizer::optimize('esto no es una imagen');
    }

    public function test_si_no_cabe_ni_en_el_minimo_avisa_en_vez_de_guardar_algo_pesado(): void
    {
        $this->expectException(RuntimeException::class);

        // Un límite imposible (1 KB) para una imagen con ruido
        ProductImageOptimizer::optimize($this->imagenPesada(1200, 900), 1024);
    }

    public function test_la_orientacion_exif_se_corrige(): void
    {
        $img = imagecreatetruecolor(200, 100);   // ancha
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();

        $resultado = ProductImageOptimizer::optimize($png, orientation: 6);   // 6 = girar 90°
        [$ancho, $alto] = getimagesizefromstring($resultado);

        $this->assertSame([100, 200], [$ancho, $alto]);
    }

    // ---------------- Formulario del producto ----------------

    public function test_subir_una_imagen_pesada_la_guarda_como_webp_de_400_kb_o_menos(): void
    {
        // ~6.5 MB sin comprimir: pesa mucho, pero cabe en el tope de subida (10 MB)
        $subida = $this->archivo($this->imagenPesada(1800, 1200), 'foto-grande.png');

        Livewire::test(EditProduct::class, ['record' => $this->producto->getRouteKey()])
            ->fillForm(['images' => [['path' => [$subida], 'alt' => 'Cubeta', 'is_primary' => true]]])
            ->call('save')
            ->assertHasNoFormErrors();

        $imagen = $this->producto->images()->sole();

        $this->assertStringStartsWith(ProductImage::UPLOAD_DIRECTORY.'/', $imagen->path);
        $this->assertStringEndsWith('.webp', $imagen->path);
        $this->assertStringContainsString('pourthane-ns', $imagen->path);
        $this->assertTrue(Storage::disk('site')->exists($imagen->path));
        $this->assertLessThanOrEqual(400 * 1024, Storage::disk('site')->size($imagen->path));
        $this->assertSame('Cubeta', $imagen->alt);
        $this->assertTrue($imagen->is_primary);
    }

    public function test_el_aviso_recomienda_webp_y_dice_el_peso_maximo(): void
    {
        Livewire::test(EditProduct::class, ['record' => $this->producto->getRouteKey()])
            ->assertSee('Imágenes del producto');

        $this->assertStringContainsString('WebP', $this->textoDeAyuda());
        $this->assertStringContainsString('400 KB', $this->textoDeAyuda());
    }

    private function textoDeAyuda(): string
    {
        return (string) file_get_contents(app_path('Filament/Resources/Products/Schemas/ProductForm.php'));
    }

    public function test_un_archivo_que_no_es_imagen_no_pasa_la_regla_de_imagenes(): void
    {
        $falla = null;

        (new OptimizableImage)->validate(
            'path',
            UploadedFile::fake()->create('ficha.pdf', 100, 'application/pdf'),
            function (string $mensaje) use (&$falla) {
                $falla = $mensaje;
            },
        );

        $this->assertSame('El archivo no es una imagen válida.', $falla);
    }

    public function test_una_imagen_normal_pasa_la_regla(): void
    {
        $falla = null;

        (new OptimizableImage)->validate(
            'path',
            UploadedFile::fake()->image('foto.jpg', 800, 600),
            function (string $mensaje) use (&$falla) {
                $falla = $mensaje;
            },
        );

        $this->assertNull($falla);
    }

    public function test_el_formulario_de_imagenes_solo_acepta_jpeg_png_y_webp(): void
    {
        $codigo = (string) file_get_contents(app_path('Filament/Resources/Products/Schemas/ProductForm.php'));

        $this->assertStringContainsString("'image/jpeg', 'image/png', 'image/webp'", $codigo);
    }

    public function test_solo_una_imagen_es_la_principal(): void
    {
        ProductImage::create(['product_id' => $this->producto->id, 'path' => 'images/productos/a.jpg', 'is_primary' => true]);
        $segunda = ProductImage::create(['product_id' => $this->producto->id, 'path' => 'images/productos/b.jpg', 'is_primary' => true]);

        $this->assertSame([false, true], $this->producto->images()->orderBy('id')->pluck('is_primary')->all());
        $this->assertSame($segunda->id, $this->producto->primaryImage()->value('id'));
    }

    public function test_si_se_borra_la_principal_la_siguiente_la_releva(): void
    {
        $a = ProductImage::create(['product_id' => $this->producto->id, 'path' => 'images/productos/a.jpg', 'is_primary' => true, 'sort_order' => 0]);
        $b = ProductImage::create(['product_id' => $this->producto->id, 'path' => 'images/productos/b.jpg', 'is_primary' => false, 'sort_order' => 1]);

        $a->delete();

        $this->assertTrue($b->fresh()->is_primary);
    }

    public function test_borrar_una_imagen_subida_borra_su_archivo_pero_no_el_de_una_original(): void
    {
        $subida = ProductImage::UPLOAD_DIRECTORY.'/prueba.webp';
        Storage::disk('site')->put($subida, 'x');
        Storage::disk('site')->put('images/productos/original.jpg', 'x');

        $imgSubida = ProductImage::create(['product_id' => $this->producto->id, 'path' => $subida]);
        $imgOriginal = ProductImage::create(['product_id' => $this->producto->id, 'path' => 'images/productos/original.jpg']);

        $imgSubida->delete();
        $imgOriginal->delete();

        $this->assertFalse(Storage::disk('site')->exists($subida));
        $this->assertTrue(Storage::disk('site')->exists('images/productos/original.jpg'));
    }

    public function test_un_archivo_subido_compartido_por_dos_imagenes_no_se_borra_mientras_se_use(): void
    {
        $ruta = ProductImage::UPLOAD_DIRECTORY.'/compartida.webp';
        Storage::disk('site')->put($ruta, 'x');

        $uno = ProductImage::create(['product_id' => $this->producto->id, 'path' => $ruta]);
        ProductImage::create(['product_id' => $this->producto->id, 'path' => $ruta]);

        $uno->delete();

        $this->assertTrue(Storage::disk('site')->exists($ruta));
    }

    // ---------------- Fichas PDF ----------------

    public function test_la_ficha_tecnica_se_sube_como_pdf_con_nombre_legible(): void
    {
        $pdf = UploadedFile::fake()->create('Hoja Tecnica FINAL (2).pdf', 500, 'application/pdf');

        Livewire::test(EditProduct::class, ['record' => $this->producto->getRouteKey()])
            ->fillForm(['tech_sheet_pdf' => $pdf])
            ->call('save')
            ->assertHasNoFormErrors();

        $ruta = $this->producto->fresh()->tech_sheet_pdf;

        $this->assertStringStartsWith('fichas/pourthane-ns-ficha-tecnica-', $ruta);
        $this->assertStringEndsWith('.pdf', $ruta);
        $this->assertTrue(Storage::disk('site')->exists($ruta));
    }

    public function test_un_archivo_que_no_es_pdf_se_rechaza_como_ficha(): void
    {
        $imagen = UploadedFile::fake()->image('ficha.png');

        Livewire::test(EditProduct::class, ['record' => $this->producto->getRouteKey()])
            ->fillForm(['tech_sheet_pdf' => $imagen])
            ->call('save')
            ->assertHasFormErrors(['tech_sheet_pdf']);
    }

    public function test_un_pdf_de_mas_de_10_mb_se_rechaza(): void
    {
        $pdf = UploadedFile::fake()->create('enorme.pdf', 11 * 1024, 'application/pdf');

        Livewire::test(EditProduct::class, ['record' => $this->producto->getRouteKey()])
            ->fillForm(['safety_sheet_pdf' => $pdf])
            ->call('save')
            ->assertHasFormErrors(['safety_sheet_pdf']);
    }

    public function test_cambiar_la_ficha_no_borra_el_pdf_anterior_que_otros_productos_pueden_usar(): void
    {
        Storage::disk('site')->put('fichas/compartida.pdf', 'pdf');
        $this->producto->update(['tech_sheet_pdf' => 'fichas/compartida.pdf']);

        Livewire::test(EditProduct::class, ['record' => $this->producto->getRouteKey()])
            ->fillForm(['tech_sheet_pdf' => UploadedFile::fake()->create('nueva.pdf', 50, 'application/pdf')])
            ->call('save');

        $this->assertTrue(Storage::disk('site')->exists('fichas/compartida.pdf'));
    }

    public function test_la_ficha_subida_aparece_en_la_pagina_del_producto(): void
    {
        $this->producto->update(['tech_sheet_pdf' => 'fichas/pourthane.pdf']);

        $this->get('/producto/pourthane-ns')
            ->assertOk()
            ->assertSee('fichas/pourthane.pdf', false);
    }

    // ---------------- Especificaciones ----------------

    public function test_las_especificaciones_se_editan_como_pares_y_se_guardan_como_lista(): void
    {
        Livewire::test(EditProduct::class, ['record' => $this->producto->getRouteKey()])
            ->fillForm(['specs' => ['Color' => 'Rojo', 'Presentación' => 'Cubeta 19 L']])
            ->call('save')
            ->assertHasNoFormErrors();

        $specs = $this->producto->fresh()->specs;

        $this->assertSame('Rojo', $specs['Color']);
        $this->assertSame('Cubeta 19 L', $specs['Presentación']);
    }

    public function test_el_dato_interno_imagen_origen_no_se_muestra_y_no_se_pierde(): void
    {
        $componente = Livewire::test(EditProduct::class, ['record' => $this->producto->getRouteKey()]);

        $filas = collect($componente->get('data.specs'))->pluck('value', 'key');

        $this->assertFalse($filas->has('imagen_origen'));
        $this->assertSame('Gris', $filas->get('Color'));

        $componente->call('save');

        $this->assertSame('PNS.jpg', $this->producto->fresh()->specs['imagen_origen']);
    }

    public function test_las_filas_vacias_de_especificaciones_se_descartan(): void
    {
        Livewire::test(EditProduct::class, ['record' => $this->producto->getRouteKey()])
            ->fillForm(['specs' => ['Color' => 'Rojo', '' => 'huérfano', 'Peso' => '']])
            ->call('save');

        $this->assertSame(['imagen_origen' => 'PNS.jpg', 'Color' => 'Rojo'], $this->producto->fresh()->specs);
    }

    public function test_la_ficha_muestra_las_especificaciones_como_tabla(): void
    {
        $this->get('/producto/pourthane-ns')
            ->assertOk()
            ->assertSee('Color')
            ->assertSee('Gris')
            ->assertDontSee('imagen_origen');
    }

    // ---------------- Precio de comparación ----------------

    public function test_con_precio_de_comparacion_mayor_se_muestra_tachado_con_el_ahorro(): void
    {
        $this->producto->update(['price' => 800, 'compare_at_price' => 1000]);

        $this->assertTrue($this->producto->fresh()->hasDiscount());
        $this->assertSame(20, $this->producto->fresh()->discountPercent());

        $this->get('/producto/pourthane-ns')
            ->assertOk()
            ->assertSee('<s>$1,000.00</s>', false)
            ->assertSee('Ahorras 20%');

        $this->get('/catalogo')
            ->assertOk()
            ->assertSee('<s>$1,000.00</s>', false)
            ->assertSee('-20%');
    }

    public function test_sin_precio_de_comparacion_no_se_muestra_nada_tachado(): void
    {
        $this->get('/producto/pourthane-ns')->assertOk()->assertDontSee('Ahorras')->assertDontSee('<s>', false);
    }

    public function test_un_precio_de_comparacion_menor_o_igual_al_actual_no_se_muestra(): void
    {
        foreach ([500, 800] as $comparacion) {
            $this->producto->update(['price' => 800, 'compare_at_price' => $comparacion]);

            $this->assertFalse($this->producto->fresh()->hasDiscount());
            $this->get('/producto/pourthane-ns')->assertDontSee('Ahorras');
        }
    }

    public function test_el_formulario_no_deja_guardar_un_precio_de_comparacion_menor_al_precio(): void
    {
        Livewire::test(EditProduct::class, ['record' => $this->producto->getRouteKey()])
            ->fillForm(['price' => 800, 'compare_at_price' => 500])
            ->call('save')
            ->assertHasFormErrors(['compare_at_price']);

        $this->assertNull($this->producto->fresh()->compare_at_price);
    }

    public function test_el_precio_de_comparacion_es_opcional(): void
    {
        Livewire::test(EditProduct::class, ['record' => $this->producto->getRouteKey()])
            ->fillForm(['compare_at_price' => null])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_el_porcentaje_se_redondea_y_un_producto_sin_precio_no_tiene_oferta(): void
    {
        $this->producto->update(['price' => 2000, 'compare_at_price' => 2999.90]);
        $this->assertSame(33, $this->producto->fresh()->discountPercent());

        $this->producto->update(['price' => 0, 'compare_at_price' => 100]);
        $this->assertFalse($this->producto->fresh()->hasDiscount());
    }
}
