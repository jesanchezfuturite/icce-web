<?php

namespace Database\Seeders\Support;

use App\Console\Commands\FetchFichasCommand;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Str;

/**
 * Normaliza el catálogo extraído de icce.com.mx (database/data/icce_catalog.json)
 * a filas listas para sembrar. CatalogSeeder y UrlRedirectSeeder comparten esta
 * clase para que el slug de un producto y su 301 nunca se desincronicen.
 *
 * IMPORTANTE: el sitio actual no publica precios ni existencias. Los valores de
 * `price` y `stock_qty` son marcadores deterministas para poder ejercitar el
 * motor Comprar vs. Cotizar; se reemplazan con la carga real del ERP.
 */
final class LegacyCatalog
{
    /** Rango de precio placeholder en MXN por categoría. */
    private const PRICE_RANGES = [
        'llanas' => [450, 2800],
        'flotas' => [900, 4500],
        'jaladores' => [700, 3200],
        'ranuradoras' => [600, 2600],
        'texturizadores' => [800, 3500],
        'aditamentos-y-extensiones' => [250, 2200],
        'aspas-y-discos-de-flotado' => [1200, 9800],
        'control-de-juntas' => [800, 6500],
        'morteros-de-reparacion' => [600, 4800],
        'impermeabilizantes' => [900, 7200],
        'membranas-de-curado' => [700, 5400],
        'epoxicos-para-anclaje' => [1100, 8600],
        'desmoldantes' => [500, 3900],
        'barrera-de-vapor' => [2500, 14000],
        'estampado-de-concreto' => [900, 6800],
        'transferencia-de-carga' => [1500, 12000],
        'desbaste-y-abrillantado' => [1800, 24000],
        'corte-de-concreto' => [1500, 18000],
    ];

    /** Unidad de venta propia del giro, por categoría. */
    private const UNITS = [
        'control-de-juntas' => 'cubeta',
        'morteros-de-reparacion' => 'saco',
        'impermeabilizantes' => 'cubeta',
        'membranas-de-curado' => 'tambor',
        'desmoldantes' => 'tambor',
        'epoxicos-para-anclaje' => 'juego',
        'barrera-de-vapor' => 'rollo',
    ];

    /** Palabras clave del nombre => marca. El orden importa: gana la primera. */
    private const BRAND_KEYWORDS = [
        'somero-enterprises' => ['REGLA LASER', 'REGLA LÁSER', 'COPPERHEAD', 'MINISCREED', 'SRS ', 'STS11', 'S 158', 'S-158', 'S10A', 'S15R', 'S22EZ', 'S28EZ', 'S240', 'S485', 'S940'],
        'mapei' => ['MAPEI', 'MAPELASTIC', 'MAPEFER', 'PLANISEAL', 'PLANITOP', 'ULTRAPLAN', 'PRIMER G', 'ECO PRIM'],
        'sika' => ['SIKA'],
        'euclid-chemical' => ['EUCO', 'EUCLID', 'DURAL '],
        'master-builders-solutions' => ['MASTERSEAL', 'MASTERKURE', 'MASTER FINISH', 'MASTER BUILDERS', 'NP1'],
        'tremco' => ['VULKEM'],
        'w-r-meadows' => ['DECK-O', 'DECK O', 'POURTHANE', 'SEALTIGHT', 'CERAMAR', 'FIBRE EXPANSION',
            'KOOL-ROD', 'BACKER ROD', 'SAFESEAL', 'REZIWELD', 'REZI-WELD', 'PRECON', 'MEADOW',
            'MEL-ROL', 'MEL-PRIME', 'HYDRALASTIC', 'HYDRACURE', 'DUOGARD', 'FUTURA',
            '1600 WHITE', '1300 CLEAR', 'BEM MEMBRANO'],
        'cts-rapid-set' => ['RAPID SET', 'RS FLEXIBLE', 'RS SELF', 'TILT FINISH', 'CONCRETE LEVELER',
            'CEMENT ALL', 'MORTARMIX', 'MORTAR MIX', 'CONCRETE MIX', 'WUNDERFIXX', 'TRU PC',
            'TRU GRAY', 'TXP ', 'LEVELFLOR', 'ACRYLIC PRIMER'],
        'cim-industries' => ['CIM '],
        // ALLANADORA va aquí y no en Kraft Tool porque contiene la subcadena
        // "LLANA": las máquinas del sitio anterior son modelos CRT/CT Husqvarna.
        'husqvarna-construction' => ['HUSQVARNA', 'ALLANADORA', 'DC5000', 'DC500', 'PG ', 'BS75'],
        'pna-construction' => ['DIAMOND DOWEL'],
        'dayton-superior' => ['SPEED DOWEL', 'SPEEDDOWEL'],
        'aztec-products' => ['AZTEC', 'BUFADORA'],
        'adhesives-technology' => ['ULTRABOND', 'CRACKBOND'],
        'wacker-neuson' => ['WACKER', 'BAILARINA', 'PLACA VIBRATORIA', 'RODILLO VIBRADOR', 'VIBRADOR',
            'REGLA VIBRATORIA', 'TORRE DE ILUMINACION', 'GENERADOR', 'DEMOLEDOR', 'CARRETILLA MOTORIZADA'],
        'kraft-tool' => ['LLANA', 'FLOTA', 'JALADOR', 'RASTRILLO', 'TEXTURIZ', 'RANURADOR', 'ADAPTADOR', 'EXTENSION', 'EXTENSIÓN', 'MEZCLADORA', 'SQUEEGE', 'CABEZAL', 'SOPORTE', 'FRESNO', 'DISCO DE FLOTADO'],
    ];

    /**
     * @return list<array{
     *     old_path:string, category_slug:string, name:string, slug:string, sku:string,
     *     brand_slug:?string, description:?string, unit:string, price:float,
     *     stock_qty:int, is_on_demand:bool, is_rental:bool, is_for_sale:bool,
     *     rental_coverage:?string, legacy_image:?string
     * }>
     */
    public static function rows(): array
    {
        $raw = json_decode(file_get_contents(database_path('data/icce_catalog.json')), true);
        $categoryByLegacy = CategorySeeder::legacyMap();
        $rentalCoverage = CategorySeeder::rentalCoverageMap();
        $datasheets = self::datasheets();

        $rows = [];
        $seenSlugs = [];
        $seenSkus = [];
        $skuCounters = [];

        foreach ($raw as $item) {
            $folder = $item['folder'] ?? null;

            // Sin carpeta o carpeta desconocida = página de aterrizaje de categoría,
            // no una ficha de producto. Los 301 de esas URLs los arma UrlRedirectSeeder.
            if ($folder === null || ! isset($categoryByLegacy[$folder])) {
                continue;
            }

            $categorySlug = $categoryByLegacy[$folder];
            $name = self::cleanName($item['name']);

            if ($name === '') {
                continue;
            }

            // El sitio anterior mezcla en una sola carpeta las máquinas
            // allanadoras con las aspas y discos que les montan. Las máquinas
            // son equipo de renta, no producto de venta.
            if ($categorySlug === 'aspas-y-discos-de-flotado' && preg_match('/^allanadora/iu', $name)) {
                $categorySlug = 'allanadoras';
            }

            $slug = Str::slug(Str::limit($name, 180, ''));

            if ($slug === '' || isset($seenSlugs[$slug])) {
                continue; // duplicado real del sitio viejo (misma ficha en dos rutas)
            }

            $seenSlugs[$slug] = true;

            $coverage = $rentalCoverage[$categorySlug] ?? null;
            $isRental = $coverage !== null;
            $seed = crc32($slug);

            $rows[] = [
                'old_path' => $item['old_path'],
                'category_slug' => $categorySlug,
                'name' => $name,
                'slug' => $slug,
                'sku' => self::sku($item['sku'] ?? null, $categorySlug, $skuCounters, $seenSkus),
                'brand_slug' => self::brandFor($name),
                'description' => self::cleanDescription($item['desc'] ?? null),
                'unit' => $isRental ? 'equipo' : (self::UNITS[$categorySlug] ?? 'pieza'),
                'price' => $isRental ? 0.0 : self::price($categorySlug, $seed),
                'legacy_image' => $item['img'] ?? null,
                'image' => self::localImage($item['old_path'], $item['img'] ?? null),
                'tech_sheet_pdf' => $datasheets[$item['old_path']] ?? null,
                'is_rental' => $isRental,
                'is_for_sale' => ! $isRental,
                'rental_coverage' => $coverage,
                ...self::stockProfile($isRental, $seed),
            ];
        }

        return $rows;
    }

    /**
     * Ficha técnica por página, tomada del mapa extraído del sitio anterior.
     * Sólo se asocia si el PDF está presente en disco: el repositorio no
     * versiona los 88 MB de fichas (ver `icce:fetch-fichas`).
     *
     * @return array<string, string>
     */
    private static function datasheets(): array
    {
        $file = database_path('data/icce_datasheets.json');

        if (! file_exists($file)) {
            return [];
        }

        $sheets = [];

        foreach (json_decode(file_get_contents($file), true) as $oldPath => $remotes) {
            foreach ($remotes as $remote) {
                $relative = 'fichas/'.FetchFichasCommand::flatten($remote);

                if (file_exists(public_path($relative))) {
                    $sheets[$oldPath] = $relative;
                    break; // la primera es la ficha principal del producto
                }
            }
        }

        return $sheets;
    }

    /**
     * Las rutas de imagen del sitio viejo son relativas a la carpeta de cada
     * ficha. Se resuelven y se aplanan al nombre con el que quedaron guardadas
     * en public/images/productos/ (ver el script de descarga en el README).
     */
    private static function localImage(string $oldPath, ?string $img): ?string
    {
        if ($img === null || $img === '' || str_starts_with($img, 'http')) {
            return null;
        }

        $base = trim(dirname(ltrim($oldPath, '/')), '.');
        $segments = [];

        foreach (explode('/', ($base !== '' ? $base.'/' : '').$img) as $segment) {
            match (true) {
                $segment === '..' => array_pop($segments),
                $segment === '.' || $segment === '' => null,
                default => $segments[] = $segment,
            };
        }

        $file = 'images/productos/'.str_replace('/', '_', implode('/', $segments));

        return file_exists(public_path($file)) ? $file : null;
    }

    private static function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        // El sitio viejo arrastra sufijos de plantilla en varios títulos
        $name = preg_replace('/\s*\|\s*(Venta|Renta)(\s*y\s*Renta)?\s*$/iu', '', $name);

        return trim($name, " \t\n\r\0\x0B-|");
    }

    private static function cleanDescription(?string $desc): ?string
    {
        if ($desc === null) {
            return null;
        }

        $desc = trim(preg_replace('/\s+/u', ' ', $desc));

        return $desc === '' ? null : $desc;
    }

    private static function brandFor(string $name): ?string
    {
        $haystack = mb_strtoupper($name);

        foreach (self::BRAND_KEYWORDS as $brandSlug => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $brandSlug;
                }
            }
        }

        return null;
    }

    /**
     * SKU real del sitio si existe; si no, uno generado y estable por categoría.
     * El sitio actual reutiliza el mismo código en fichas distintas (p. ej.
     * KRASK401 aparece en el disco R36 y en el R47), así que se desambigua con
     * un sufijo en vez de perder el producto.
     */
    private static function sku(?string $found, string $categorySlug, array &$counters, array &$seen): string
    {
        if ($found !== null && $found !== '') {
            $sku = mb_strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', $found));
        } else {
            $prefix = mb_strtoupper(substr(preg_replace('/[^a-z]/', '', $categorySlug), 0, 3));
            $counters[$prefix] = ($counters[$prefix] ?? 0) + 1;
            $sku = sprintf('ICCE-%s-%04d', $prefix, $counters[$prefix]);
        }

        $base = $sku;
        $suffix = 1;

        while (isset($seen[$sku])) {
            $sku = $base.'-'.(++$suffix);
        }

        $seen[$sku] = true;

        return $sku;
    }

    /** Precio placeholder determinista: mismo slug => mismo precio en cada seed. */
    private static function price(string $categorySlug, int $seed): float
    {
        [$min, $max] = self::PRICE_RANGES[$categorySlug] ?? [500, 5000];
        $value = $min + ($seed % max(1, $max - $min));

        return round($value / 10) * 10 - 0.10;
    }

    /**
     * Distribuye existencias para que el catálogo sembrado ejercite los dos
     * caminos del carrito híbrido: compra directa y cotización obligatoria.
     */
    private static function stockProfile(bool $isRental, int $seed): array
    {
        if ($isRental) {
            return ['stock_qty' => 1 + ($seed % 4), 'is_on_demand' => false];
        }

        $bucket = $seed % 100;

        return match (true) {
            $bucket < 15 => ['stock_qty' => 0, 'is_on_demand' => true],   // bajo pedido
            $bucket < 25 => ['stock_qty' => 0, 'is_on_demand' => false],  // agotado
            $bucket < 40 => ['stock_qty' => 1 + ($seed % 5), 'is_on_demand' => false], // últimas piezas
            default => ['stock_qty' => 8 + ($seed % 120), 'is_on_demand' => false],
        };
    }
}
