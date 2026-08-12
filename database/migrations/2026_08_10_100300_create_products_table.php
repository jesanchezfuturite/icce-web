<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            $table->string('sku', 50)->unique();
            $table->string('name', 200);
            $table->string('slug', 200)->unique();
            $table->string('short_description', 255)->nullable();
            $table->text('description')->nullable();

            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            // Unidad de venta del giro: pieza, saco, cubeta, tambor, m2, juego...
            $table->string('unit', 20)->default('pieza');

            $table->integer('stock_qty')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);

            // Motor de decisión Comprar vs. Cotizar (REQ-01 / REQ-02)
            $table->unsignedInteger('max_direct_purchase')->default(10);
            $table->boolean('is_on_demand')->default(false)->index();

            // Catálogo de renta: informativo, sin motor de pago (REQ-06)
            $table->boolean('is_rental')->default(false)->index();
            $table->boolean('is_for_sale')->default(true)->index();
            $table->string('rental_coverage', 20)->nullable();

            $table->string('tech_sheet_pdf', 255)->nullable();
            $table->string('safety_sheet_pdf', 255)->nullable();
            // Especificaciones libres por producto: {"Ancho": "36 pulgadas", ...}
            $table->json('specs')->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_featured')->default(false)->index();

            $table->string('meta_title', 191)->nullable();
            $table->string('meta_description', 255)->nullable();

            $table->timestamps();

            $table->index(['category_id', 'is_active']);
            $table->index(['brand_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
