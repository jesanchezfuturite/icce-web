<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\Support\LegacyCatalog;
use Illuminate\Database\Seeder;

/**
 * Siembra los productos extraídos de icce.com.mx.
 * Depende de BrandSeeder y CategorySeeder.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categoryIds = Category::pluck('id', 'slug');
        $brandIds = Brand::pluck('id', 'slug');
        $count = 0;

        foreach (LegacyCatalog::rows() as $row) {
            $categoryId = $categoryIds[$row['category_slug']] ?? null;

            if ($categoryId === null) {
                continue;
            }

            $product = Product::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'category_id' => $categoryId,
                    'brand_id' => $brandIds[$row['brand_slug']] ?? null,
                    'sku' => $row['sku'],
                    'name' => $row['name'],
                    'short_description' => $row['description'] ? mb_substr($row['description'], 0, 250) : null,
                    'description' => $row['description'],
                    'price' => $row['price'],
                    'unit' => $row['unit'],
                    'stock_qty' => $row['stock_qty'],
                    'low_stock_threshold' => 5,
                    'max_direct_purchase' => (int) config('icce.max_direct_purchase', 10),
                    'is_on_demand' => $row['is_on_demand'],
                    'is_rental' => $row['is_rental'],
                    'is_for_sale' => $row['is_for_sale'],
                    'rental_coverage' => $row['rental_coverage'],
                    'tech_sheet_pdf' => $row['tech_sheet_pdf'],
                    'is_active' => true,
                    'is_featured' => $count % 17 === 0,
                    'meta_title' => $row['name'].' | ICCE Rentas y Servicios',
                    'meta_description' => $row['description']
                        ? mb_substr($row['description'], 0, 155)
                        : $row['name'].' disponible en ICCE Rentas y Servicios.',
                    // Ruta de la imagen en el sitio viejo; queda registrada para la
                    // migración de assets a S3/WebP y no se sirve directamente.
                    'specs' => $row['legacy_image'] ? ['imagen_origen' => $row['legacy_image']] : null,
                ],
            );

            if ($row['image'] !== null) {
                $product->images()->updateOrCreate(
                    ['path' => $row['image']],
                    ['alt' => $row['name'], 'is_primary' => true, 'sort_order' => 0],
                );
            }

            $count++;
        }

        $this->command?->info("Productos sembrados: {$count}");
    }
}
