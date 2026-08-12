<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Casos de éxito / galería de obras atendidas (1.5 / 5.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 200)->unique();
            $table->string('client', 150)->nullable();
            $table->string('location', 150)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedInteger('area_m2')->nullable();
            $table->string('summary', 400)->nullable();
            $table->longText('body')->nullable();
            $table->string('cover_image', 255)->nullable();
            // Servicios y equipos empleados: ["Pisos superplanos", "Regla láser S-940"]
            $table->json('services')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
