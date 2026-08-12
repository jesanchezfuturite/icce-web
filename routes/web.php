<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegacyUrlController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// 2.0 / 6.0 — Secciones institucionales
Route::get('/empresa', [PageController::class, 'empresa'])->name('empresa');
Route::get('/servicios', [PageController::class, 'servicios'])->name('servicios');
Route::get('/contacto', [PageController::class, 'contacto'])->name('contacto');
// RNF-02: un formulario abierto necesita techo, aunque tenga trampa antispam
Route::post('/contacto', [ContactController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('contacto.store');
Route::get('/aviso-de-privacidad', [PageController::class, 'privacidad'])->name('privacidad');
Route::get('/politicas', [PageController::class, 'politicas'])->name('politicas');

// 3.0 — Catálogo (la búsqueda, filtros y ficha completa llegan en la fase 3)
Route::get('/catalogo', [CatalogController::class, 'index'])->name('catalogo.index');
Route::get('/catalogo/{category:slug}', [CatalogController::class, 'category'])->name('catalogo.categoria');
Route::get('/producto/{product:slug}', [CatalogController::class, 'show'])->name('producto');

// 3.4 / 3.5 — Carrito híbrido y checkout
Route::view('/carrito', 'pages.carrito')->name('carrito');
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/gracias', [CheckoutController::class, 'gracias'])->name('checkout.gracias');

// 4.0 — Renta de equipos
Route::get('/renta', [RentalController::class, 'index'])->name('renta.index');
Route::get('/renta/requisitos', [RentalController::class, 'requisitos'])->name('renta.requisitos');
Route::get('/renta/solicitar', [RentalController::class, 'solicitar'])->name('renta.solicitar');
// Va al final del grupo: si no, capturaría /renta/requisitos y /renta/solicitar
Route::get('/renta/{product:slug}', [RentalController::class, 'show'])->name('renta.equipo');

// 5.0 — Recursos y contenido
Route::get('/proyectos', [ProjectController::class, 'index'])->name('proyectos.index');
Route::get('/proyectos/{project:slug}', [ProjectController::class, 'show'])->name('proyectos.show');
Route::view('/descargas', 'pages.descargas')->name('descargas');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{post:slug}', [BlogController::class, 'show'])->name('blog.show');

Route::get('/marcas', [BrandController::class, 'index'])->name('marcas.index');
Route::get('/marcas/{brand:slug}', [BrandController::class, 'show'])->name('marcas.show');

// 7.0 — Portal de cliente. El backoffice tiene su propio acceso en /admin.
Route::middleware('guest')->group(function () {
    Route::get('/ingresar', [LoginController::class, 'show'])->name('login');
    Route::post('/ingresar', [LoginController::class, 'store']);
});

Route::post('/salir', [LoginController::class, 'destroy'])->name('logout');

Route::middleware('auth')->prefix('portal')->name('portal.')->group(function () {
    Route::get('/', [PortalController::class, 'index'])->name('index');
    Route::get('/pedido/{order:folio}', [PortalController::class, 'show'])->name('pedido');
});

// SEO
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

/*
 * Migración del sitio anterior (TRD 4.3). Va al final a propósito: sólo se
 * consulta el mapa de redirecciones cuando ninguna ruta real coincidió.
 */
Route::fallback(LegacyUrlController::class);
