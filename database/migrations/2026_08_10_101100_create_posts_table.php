<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog técnico de aplicación de concreto (1.6 / 5.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->string('slug', 200)->unique();
            $table->string('topic', 60)->nullable()->index();
            $table->string('excerpt', 400)->nullable();
            $table->longText('body')->nullable();
            $table->string('cover_image', 255)->nullable();
            $table->unsignedSmallInteger('reading_minutes')->default(5);
            $table->boolean('is_featured')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('meta_title', 191)->nullable();
            $table->string('meta_description', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
