<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Marcas que ICCE distribuye. Salen del carrusel de logos y de las páginas
 * de distribución del sitio actual (icce.com.mx/ICCE-Distribucion/).
 */
class BrandSeeder extends Seeder
{
    public const BRANDS = [
        ['name' => 'Somero Enterprises', 'logo' => 'images/marcas/Somero.png', 'website' => 'https://www.somero.com', 'featured' => true,
            'description' => 'Reglas láser para nivelación de pisos superplanos. ICCE es distribuidor en México.'],
        ['name' => 'Kraft Tool', 'logo' => 'images/marcas/KraftTool.png', 'website' => 'https://www.krafttool.com', 'featured' => true,
            'description' => 'Herramienta profesional para acabado de concreto: llanas, flotas, jaladores y aditamentos.'],
        ['name' => 'Husqvarna Construction', 'logo' => 'images/marcas/Husqvarna.png', 'website' => 'https://www.husqvarnacp.com', 'featured' => true,
            'description' => 'Equipos de corte, desbaste, pulido y aspiración para concreto.'],
        ['name' => 'CTS Rapid Set', 'logo' => 'images/marcas/Rapidset.png', 'website' => 'https://www.ctscement.com', 'featured' => true,
            'description' => 'Cementos y morteros de fraguado rápido para reparación estructural.'],
        ['name' => 'W. R. Meadows', 'logo' => 'images/marcas/WR_Meadows.png', 'website' => 'https://www.wrmeadows.com', 'featured' => true,
            'description' => 'Membranas de curado, selladores de juntas y materiales de expansión.'],
        ['name' => 'Sika', 'logo' => 'images/marcas/Sika.png', 'website' => 'https://mex.sika.com', 'featured' => true,
            'description' => 'Selladores, adhesivos y sistemas de impermeabilización.'],
        ['name' => 'Mapei', 'logo' => 'images/marcas/mapei.jpg', 'website' => 'https://www.mapei.com', 'featured' => true,
            'description' => 'Morteros de nivelación, impermeabilizantes y productos de reparación.'],
        ['name' => 'Master Builders Solutions', 'logo' => 'images/marcas/Basf.png', 'website' => 'https://www.master-builders-solutions.com', 'featured' => true,
            'description' => 'Aditivos, selladores y sistemas de protección de concreto (antes BASF).'],
        ['name' => 'Euclid Chemical', 'logo' => 'images/marcas/Euco.png', 'website' => 'https://www.euclidchemical.com', 'featured' => true,
            'description' => 'Aditivos, curadores y selladores de juntas para pisos industriales.'],
        ['name' => 'Tremco', 'logo' => null, 'website' => 'https://www.tremcosealants.com', 'featured' => false,
            'description' => 'Selladores de poliuretano de alto desempeño (línea Vulkem).'],
        ['name' => 'PNA Construction', 'logo' => 'images/marcas/PNA.png', 'website' => 'https://www.pna-inc.com', 'featured' => true,
            'description' => 'Sistemas de transferencia de carga Diamond Dowel para juntas de contracción.'],
        ['name' => 'Dayton Superior', 'logo' => 'images/marcas/Dayton.png', 'website' => 'https://www.daytonsuperior.com', 'featured' => false,
            'description' => 'Accesorios para concreto y sistemas Speed Dowel.'],
        ['name' => 'Bekaert Dramix', 'logo' => 'images/marcas/Dramix.png', 'website' => 'https://www.bekaert.com', 'featured' => true,
            'description' => 'Fibras de acero para refuerzo de pisos industriales de concreto.'],
        ['name' => 'Wacker Neuson', 'logo' => 'images/marcas/Wacker_Neuson.png', 'website' => 'https://www.wackerneuson.com', 'featured' => true,
            'description' => 'Equipos vibratorios, compactación y maquinaria ligera de construcción.'],
        ['name' => 'Adhesives Technology', 'logo' => 'images/marcas/AdhesivesTechnologys.png', 'website' => 'https://www.atcepoxy.com', 'featured' => false,
            'description' => 'Anclajes epóxicos y adhesivos estructurales.'],
        ['name' => 'CIM Industries', 'logo' => 'images/marcas/CIM_Industries.png', 'website' => 'https://www.cimindustries.com', 'featured' => false,
            'description' => 'Recubrimientos e impermeabilizantes de poliuretano.'],
        ['name' => 'Face Construction Technologies', 'logo' => 'images/marcas/Face.png', 'website' => 'https://www.allenface.com', 'featured' => false,
            'description' => 'Instrumentos de medición de planicidad y nivelación (F-numbers).'],
        ['name' => 'Aztec Products', 'logo' => null, 'website' => 'https://www.aztecproducts.com', 'featured' => false,
            'description' => 'Bufadoras y equipos de abrillantado de pisos.'],
        ['name' => 'Cipsa', 'logo' => 'images/marcas/Cipsa.png', 'website' => null, 'featured' => false,
            'description' => 'Productos químicos para la construcción.'],
    ];

    public function run(): void
    {
        foreach (self::BRANDS as $i => $brand) {
            Brand::updateOrCreate(
                ['slug' => Str::slug($brand['name'])],
                [
                    'name' => $brand['name'],
                    'website' => $brand['website'],
                    'description' => $brand['description'],
                    'logo_path' => $brand['logo'],
                    'is_featured' => $brand['featured'],
                    'sort_order' => $i,
                ],
            );
        }
    }
}
