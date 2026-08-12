<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mapa de URLs del sitio estático anterior hacia la nueva estructura (TRD 4.3).
 * Un middleware consulta esta tabla y emite el 301 permanente (RNF-03).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('url_redirects', function (Blueprint $table) {
            $table->id();
            // 500 chars utf8mb4 = 2000 bytes, dentro del límite de 3072 de InnoDB
            $table->string('old_path', 500)->unique();
            // Nulo cuando status_code es 410: la URL vieja se retira sin destino
            $table->string('new_path', 500)->nullable();
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            // Telemetría para detectar rutas viejas que siguen recibiendo tráfico
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('url_redirects');
    }
};
