<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Folio visible para el cliente (VD-2026-00014 / COT-2026-00014)
            $table->string('folio', 30)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('order_type', 20)->index();
            $table->string('status', 20)->default('pending')->index();

            // Snapshot de contacto: la orden no debe romperse si el usuario cambia sus datos
            $table->string('customer_name', 191);
            $table->string('customer_email', 191);
            $table->string('customer_phone', 50)->nullable();
            $table->string('customer_company', 191)->nullable();

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('shipping_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->char('currency', 3)->default('MXN');

            $table->json('shipping_address')->nullable();
            $table->json('billing_address')->nullable();

            // Seguimiento logístico (REQ-04 / REQ-05)
            $table->date('estimated_delivery_date')->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('carrier', 100)->nullable();

            // Pasarela (REQ-03)
            $table->string('payment_provider', 30)->nullable();
            $table->string('payment_reference', 191)->nullable();
            $table->string('payment_status', 30)->nullable();

            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            // Vigencia de la cotización enviada por el agente (REQ-09)
            $table->date('quote_valid_until')->nullable();

            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['order_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
