<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensajes del formulario general (6.1).
 *
 * Se guardan además de enviarse por correo: si el servidor de correo falla o
 * el aviso se pierde en una bandeja saturada, el mensaje sigue estando y nadie
 * pierde un prospecto por un problema de infraestructura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('company', 150)->nullable();
            $table->string('email', 191);
            $table->string('phone', 50);
            $table->string('location', 150)->nullable();
            $table->string('subject', 80);
            $table->text('message');

            $table->string('status', 20)->default('new')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('internal_notes')->nullable();

            // Trazabilidad mínima para detectar abuso
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
