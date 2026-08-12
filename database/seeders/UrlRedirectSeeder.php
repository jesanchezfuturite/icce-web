<?php

namespace Database\Seeders;

use App\Models\UrlRedirect;
use Database\Seeders\Support\LegacyCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Mapa de migración SEO (TRD 4.3 / RNF-03).
 *
 * Tres orígenes, en este orden de prioridad:
 *   1. URLs comprometidas del sitio actual -> 410 Gone (nunca 301: heredarían
 *      a la nueva estructura la señal de spam que Google ya vio en ellas).
 *   2. Fichas de producto -> /producto/{slug} o /renta/{slug}.
 *   3. Páginas de aterrizaje y secciones institucionales -> mapa explícito.
 *
 * Lo que no cae en ninguno se siembra INACTIVO con new_path nulo para que el
 * equipo lo resuelva a mano en el backoffice antes de salir a producción.
 */
class UrlRedirectSeeder extends Seeder
{
    /** Aterrizajes de categoría del sitio viejo. */
    private const LANDING_MAP = [
        '/Acelerantes-Retardantes-para-Concreto.html' => '/catalogo/materiales-y-quimicos',
        '/Aditamentos-Para-Herramientas-de-Concreto.html' => '/catalogo/aditamentos-y-extensiones',
        '/Aspas-Llanas-para-Allanadora.html' => '/catalogo/aspas-y-discos-de-flotado',
        '/Aspiradoras-Husqvarna-Venta.html' => '/catalogo/desbaste-y-abrillantado',
        '/Barrera-de-Vapor.html' => '/catalogo/barrera-de-vapor',
        '/Brocas-Perforacion-Tomamuestras.html' => '/renta/perforadoras-y-tomamuestras',
        '/Cepillo-Para-Escobillado-Herramientas-Para-Concreto.html' => '/catalogo/texturizadores',
        '/Cortadoras-para-Concreto-Renta-y-Venta.html' => '/catalogo/corte-de-concreto',
        '/Desbastadoras-Husqvarna-Venta.html' => '/catalogo/desbaste-y-abrillantado',
        '/Desbaste-y-Abrillantado-Pads.html' => '/catalogo/desbaste-y-abrillantado',
        '/Desbaste-y-Abrillantado-Resinas.html' => '/catalogo/desbaste-y-abrillantado',
        '/Desmoldantes-para-Cimbra-Cacetons-de-Concreto.html' => '/catalogo/desmoldantes',
        '/Disco-de-Flotado-para-Allanadoras.html' => '/catalogo/aspas-y-discos-de-flotado',
        '/Discos-de-Corte-para-Concreto-Venta.html' => '/catalogo/corte-de-concreto',
        '/Epoxicos-para-Anclaje-y-Recubrimiento.html' => '/catalogo/epoxicos-para-anclaje',
        '/Estampado-de-Concreto.html' => '/catalogo/estampado-de-concreto',
        '/Flotas-Herramientas-Para-Concreto-Relleno-para-Juntas-de-Control.html' => '/catalogo/flotas',
        '/Impermeabilizantes.html' => '/catalogo/impermeabilizantes',
        '/Jaladores-Para-Concreto-Herramientas.html' => '/catalogo/jaladores',
        '/Llanas-Herramientas-Para-Concreto.html' => '/catalogo/llanas',
        '/Maquinaria-Ligera-para-la-Construccion-Renta-y-Venta-.html' => '/renta',
        '/Membranas-de-Curado-Mantas.html' => '/catalogo/membranas-de-curado',
        '/Morteros-de-Reparacion-de-Concreto.html' => '/catalogo/morteros-de-reparacion',
        '/Placa-Vibradora-Husqvarna-Venta.html' => '/renta/equipos-vibratorios',
        '/Ranuradoras-Herramientas-para-Concreto.html' => '/catalogo/ranuradoras',
        '/Rastrillos-para-Concreto-Herramientas.html' => '/catalogo/herramientas-para-concreto',
        '/Reglas-Laser-Somero-Nivelacion-Pisos-SuperPlanos-Venta-Renta.html' => '/renta/reglas-laser-somero',
        '/Sellador-para-Control-de-Juntas-de-Concreto.html' => '/catalogo/control-de-juntas',
        '/Texturizadores-de-Concreto-Herramientas.html' => '/catalogo/texturizadores',
        '/Transferencia-de-Carga-Canastillas-Pasajuntas-Speed-Dowel-Diamond-Dowel.html' => '/catalogo/transferencia-de-carga',
    ];

    /** Secciones institucionales. */
    private const INSTITUTIONAL_MAP = [
        '/index.html' => '/',
        '/Articulos-de-Investigacion-ICCE-Rentas-y-Servicios.html' => '/blog',
        '/Aviso-de-Privacidad-ICCE-RENTAS-Y-SERVICIOS.html' => '/aviso-de-privacidad',
        '/Politicas-ICCE-Rentas-y-Servicios.html' => '/politicas',
        '/Promociones-ICCE-Rentas-y-Servicios.html' => '/promociones',
        '/Servicios-ICCE-Rentas-y-Servicios.html' => '/servicios',
        '/Sucursales-ICCE-Rentas-y-Servicios.html' => '/contacto',
        '/Requisitos-para-Renta-Maquinaria-Ligera-Para-La-Construccion-ICCE-RENTAS-Y-SERVICIOS.html' => '/renta/requisitos',
        '/Requisitos-de-renta.html' => '/renta/requisitos',
        '/ICCE-Distribucion/Kraft-Tool.html' => '/marcas/kraft-tool',
        '/ICCE-Distribucion/Somero-Productos-Distribuidor-en-Mexico.html' => '/marcas/somero-enterprises',
        '/ICCE-Rentas-y-Serivio-Distribuidor-de.html' => '/marcas',
    ];

    /** Plantillas del theme Bootstrap que nunca fueron contenido real. */
    private const GONE = [
        '/Pagina-en-construccion.html',
        '/page-error.html',
        '/page-about-us.html',
        '/page-faq.html',
        '/page-pricing-tables.html',
        '/page-privacy.html',
        '/page-search-results.html',
        '/page-services.html',
        '/page-support.html',
        '/page-terms.html',
        '/portfolio-2-columns.html',
        '/portfolio-3-columns.html',
        '/portfolio-4-columns.html',
        '/portfolio-single.html',
        '/feature-hero-unit.html',
        '/checkout.html',
        '/shop/index.html',
        '/shop/single-product-page.html',
    ];

    public function run(): void
    {
        $map = [];

        // 1. Comprometidas: se retiran, no se redirigen.
        foreach ($this->compromisedUrls() as $path) {
            $map[$path] = ['new_path' => null, 'status_code' => 410, 'is_active' => true];
        }

        // 2. Fichas de producto.
        foreach (LegacyCatalog::rows() as $row) {
            if (isset($map[$row['old_path']])) {
                continue;
            }

            $prefix = $row['is_rental'] ? '/renta' : '/producto';
            $map[$row['old_path']] = [
                'new_path' => $prefix.'/'.$row['slug'],
                'status_code' => 301,
                'is_active' => true,
            ];
        }

        // 3. Aterrizajes, institucionales y plantillas muertas.
        foreach (self::LANDING_MAP + self::INSTITUTIONAL_MAP as $old => $new) {
            $map[$old] ??= ['new_path' => $new, 'status_code' => 301, 'is_active' => true];
        }

        foreach (self::GONE as $old) {
            $map[$old] ??= ['new_path' => null, 'status_code' => 410, 'is_active' => true];
        }

        // 4. Blog: cada artículo conserva su ruta bajo /blog.
        // 5. Resto: inactivo, pendiente de mapeo manual.
        foreach ($this->legacyUrls() as $path) {
            if (isset($map[$path])) {
                continue;
            }

            if (str_starts_with($path, '/Blog/')) {
                $map[$path] = [
                    'new_path' => '/blog/'.Str::slug(pathinfo($path, PATHINFO_FILENAME)),
                    'status_code' => 301,
                    'is_active' => true,
                ];

                continue;
            }

            $map[$path] = ['new_path' => null, 'status_code' => 301, 'is_active' => false];
        }

        foreach ($map as $old => $attributes) {
            UrlRedirect::updateOrCreate(['old_path' => $old], $attributes);
        }

        $pending = collect($map)->where('is_active', false)->count();
        $gone = collect($map)->where('status_code', 410)->count();

        $this->command?->info(sprintf(
            'Redirecciones: %d totales | %d activas 301 | %d retiradas 410 | %d pendientes de mapeo manual',
            count($map),
            count($map) - $gone - $pending,
            $gone,
            $pending,
        ));
    }

    /** @return list<string> */
    private function compromisedUrls(): array
    {
        $file = database_path('data/icce_compromised_urls.json');

        return file_exists($file)
            ? json_decode(file_get_contents($file), true)
            : [];
    }

    /** @return list<string> */
    private function legacyUrls(): array
    {
        $file = database_path('data/icce_legacy_urls.txt');

        if (! file_exists($file)) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', file($file)),
            fn (string $line) => $line !== '',
        ));
    }
}
