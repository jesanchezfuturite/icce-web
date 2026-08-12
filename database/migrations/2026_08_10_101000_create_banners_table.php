<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banners del hero del home (1.1). Editables desde el CMS (REQ-08).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('eyebrow', 80)->nullable();
            $table->string('title', 150);
            $table->string('subtitle', 300)->nullable();
            $table->string('image_path', 255);
            $table->string('cta_label', 60)->nullable();
            $table->string('cta_url', 255)->nullable();
            $table->string('secondary_cta_label', 60)->nullable();
            $table->string('secondary_cta_url', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            // Vigencia opcional para promociones con fecha
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
