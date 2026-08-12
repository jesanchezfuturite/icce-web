<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            // Snapshot del producto al momento de la orden: si cambia el catálogo,
            // la orden histórica conserva nombre, SKU y precio originales.
            $table->string('product_sku', 50);
            $table->string('product_name', 200);

            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            // Precio ajustado por el agente en el CRM (REQ-09); null = sin ajuste
            $table->decimal('quoted_unit_price', 10, 2)->nullable();
            $table->decimal('line_total', 10, 2)->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
