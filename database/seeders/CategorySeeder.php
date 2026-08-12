<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Taxonomía nueva del catálogo. Reordena las carpetas planas del sitio actual
 * en un árbol de dos niveles alineado con el sitemap del documento (3.1 / 3.2 / 4.0).
 *
 * `legacy` guarda la carpeta original en icce.com.mx; CatalogSeeder la usa para
 * colocar cada producto y UrlRedirectSeeder para construir los 301.
 */
class CategorySeeder extends Seeder
{
    public const TREE = [
        [
            'name' => 'Herramientas para Concreto',
            'slug' => 'herramientas-para-concreto',
            'description' => 'Herramienta profesional para colado, acabado y texturizado de concreto.',
            'children' => [
                ['name' => 'Llanas', 'slug' => 'llanas', 'legacy' => 'Herramientas-para-Concreto/Llanas-Herramientas-Para-Concreto'],
                ['name' => 'Flotas', 'slug' => 'flotas', 'legacy' => 'Herramientas-para-Concreto/Flotas-Herramientas-Para-Concreto'],
                ['name' => 'Jaladores', 'slug' => 'jaladores', 'legacy' => 'Herramientas-para-Concreto/Jaladores-de-Concreto-Herramientas'],
                ['name' => 'Ranuradoras', 'slug' => 'ranuradoras', 'legacy' => 'Herramientas-para-Concreto/Ranuradoras-Herramientas-Para-Concreto'],
                ['name' => 'Texturizadores', 'slug' => 'texturizadores', 'legacy' => 'Herramientas-para-Concreto/Texturizadores-de-Concreto-Herramientas'],
                ['name' => 'Aditamentos y Extensiones', 'slug' => 'aditamentos-y-extensiones', 'legacy' => 'Herramientas-para-Concreto/Aditamentos-para-Herramientas-de-Concreto'],
                ['name' => 'Aspas y Discos de Flotado', 'slug' => 'aspas-y-discos-de-flotado', 'legacy' => 'Allanadora-Doble-Sencilla-Llanas-Discos-Flotado-Venta-Renta-Mexico'],
            ],
        ],
        [
            'name' => 'Materiales y Químicos',
            'slug' => 'materiales-y-quimicos',
            'description' => 'Selladores, morteros, membranas y químicos para pisos industriales.',
            'children' => [
                ['name' => 'Control de Juntas', 'slug' => 'control-de-juntas', 'legacy' => 'Materiales-para-Control-de-Juntas'],
                ['name' => 'Morteros de Reparación', 'slug' => 'morteros-de-reparacion', 'legacy' => 'Morteros-de-Reparacion-de-Concreto'],
                ['name' => 'Impermeabilizantes', 'slug' => 'impermeabilizantes', 'legacy' => 'Impermeabilizantes-Industriales-Residenciales'],
                ['name' => 'Membranas de Curado', 'slug' => 'membranas-de-curado', 'legacy' => 'Membranas-de-Curado-Mantas'],
                ['name' => 'Epóxicos para Anclaje', 'slug' => 'epoxicos-para-anclaje', 'legacy' => 'Epoxicos-para-Anclaje-y-Recubrimiento'],
                ['name' => 'Desmoldantes', 'slug' => 'desmoldantes', 'legacy' => 'Desmoldantes-para-Cimbra-Caceton-Solvente-Base-Agua'],
                ['name' => 'Barrera de Vapor', 'slug' => 'barrera-de-vapor', 'legacy' => 'Barrera-de-Vapor'],
                ['name' => 'Estampado de Concreto', 'slug' => 'estampado-de-concreto', 'legacy' => 'Estampado-de-Concreto'],
            ],
        ],
        [
            'name' => 'Transferencia de Carga',
            'slug' => 'transferencia-de-carga',
            'description' => 'Canastillas pasajuntas, Speed Dowel y Diamond Dowel para juntas de contracción.',
            'legacy' => 'Transferencia-de-Carga-Canastillas-Pasajuntas-Speed-Dowel-Diamond-Dowel',
            'children' => [],
        ],
        [
            'name' => 'Desbaste y Abrillantado',
            'slug' => 'desbaste-y-abrillantado',
            'description' => 'Aditamentos, pads, resinas y metales diamantados para pulido de concreto.',
            'legacy' => 'Desbaste-Abrillantado-Aditamentos-Venta-Renta',
            'children' => [],
        ],
        [
            'name' => 'Corte de Concreto',
            'slug' => 'corte-de-concreto',
            'description' => 'Cortadoras, discos diamantados y refacciones.',
            'legacy' => 'Cortadoras-Discos-y-Refacciones',
            'children' => [],
        ],
        [
            'name' => 'Renta de Equipos',
            'slug' => 'renta-de-equipos',
            'description' => 'Maquinaria ligera y equipo especializado en renta con cobertura nacional y local.',
            'children' => [
                ['name' => 'Reglas Láser Somero', 'slug' => 'reglas-laser-somero', 'legacy' => 'Reglas-Laser-Somero-Mexico-Venta-Renta', 'coverage' => 'national'],
                ['name' => 'Allanadoras', 'slug' => 'allanadoras', 'coverage' => 'local'],
                ['name' => 'Equipos Vibratorios', 'slug' => 'equipos-vibratorios', 'legacy' => 'Renta-Venta-Equipos-Vibratorios-para-Concreto', 'coverage' => 'local'],
                ['name' => 'Perforadoras y Tomamuestras', 'slug' => 'perforadoras-y-tomamuestras', 'legacy' => 'Perforadora-Tomamuetras-Brocas', 'coverage' => 'local'],
                ['name' => 'Iluminación y Generadores', 'slug' => 'iluminacion-y-generadores', 'legacy' => 'Iluminacion-Generadores-Plancha-Bomba-Agua-Para-Soldar-Renta-Venta', 'coverage' => 'local'],
                ['name' => 'Carretillas Motorizadas', 'slug' => 'carretillas-motorizadas', 'legacy' => 'Carretilla-Motorizada-Renta-Venta', 'coverage' => 'local'],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::TREE as $rootOrder => $root) {
            $parent = Category::updateOrCreate(
                ['slug' => $root['slug']],
                [
                    'name' => $root['name'],
                    'description' => $root['description'],
                    'parent_id' => null,
                    'sort_order' => $rootOrder,
                    'meta_title' => $root['name'].' | ICCE Rentas y Servicios',
                    'meta_description' => $root['description'],
                ],
            );

            foreach ($root['children'] as $childOrder => $child) {
                Category::updateOrCreate(
                    ['slug' => $child['slug']],
                    [
                        'name' => $child['name'],
                        'parent_id' => $parent->id,
                        'sort_order' => $childOrder,
                        'meta_title' => $child['name'].' | ICCE Rentas y Servicios',
                    ],
                );
            }
        }
    }

    /** Mapa carpeta-heredada => slug de categoría nueva. */
    public static function legacyMap(): array
    {
        $map = [];

        foreach (self::TREE as $root) {
            if (isset($root['legacy'])) {
                $map[$root['legacy']] = $root['slug'];
            }

            foreach ($root['children'] as $child) {
                if (isset($child['legacy'])) {
                    $map[$child['legacy']] = $child['slug'];
                }
            }
        }

        return $map;
    }

    /** Slugs de categorías cuyos productos son de renta, con su cobertura. */
    public static function rentalCoverageMap(): array
    {
        $map = [];

        foreach (self::TREE as $root) {
            foreach ($root['children'] as $child) {
                if (isset($child['coverage'])) {
                    $map[$child['slug']] = $child['coverage'];
                }
            }
        }

        return $map;
    }
}
