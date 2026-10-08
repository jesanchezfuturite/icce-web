<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Si un equipo de renta incluye operador pasa a ser un dato de cada producto.
 *
 * Los equipos que ya tienen cobertura nacional se marcan con operador incluido,
 * que es la regla que confirmó el cliente (regla láser y distribuidora de
 * materiales). El resto queda sin operador. Es la única fila que toca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('rental_includes_operator')->default(false)->after('rental_coverage');
        });

        DB::table('products')
            ->where('rental_coverage', 'national')
            ->update(['rental_includes_operator' => true]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('rental_includes_operator');
        });
    }
};
