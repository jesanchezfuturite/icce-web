<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            BrandSeeder::class,
            CategorySeeder::class,
            CatalogSeeder::class,
            CmsSeeder::class,
            UrlRedirectSeeder::class,
        ]);

        // Órdenes, cotizaciones y leads de renta: solo fuera de producción.
        if (! app()->isProduction()) {
            $this->call(DemoOrderSeeder::class);
        }
    }
}
