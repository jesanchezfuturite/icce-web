<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_requests', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 30)->unique();
            // El equipo puede venir del catálogo o escribirse libre desde el formulario
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('equipment_name', 150);

            $table->string('client_name', 150);
            $table->string('company', 150)->nullable();
            $table->string('email', 191);
            $table->string('phone', 50);

            $table->string('location', 150);
            $table->string('coverage', 20)->default('local');

            $table->date('start_date')->nullable();
            $table->unsignedSmallInteger('rental_days')->nullable();
            $table->text('project_description')->nullable();
            $table->text('notes')->nullable();

            $table->string('status', 20)->default('new')->index();
            $table->foreignId('assigned_to')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('contacted_at')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_requests');
    }
};
