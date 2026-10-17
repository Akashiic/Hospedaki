<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conteúdo da página inicial de cada pousada: frase de destaque,
     * diferenciais e fotos de capa/galeria.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('tagline')->nullable()->after('name');
            $table->json('highlights')->nullable()->after('description');
        });

        Schema::create('property_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('caption')->nullable();
            $table->boolean('featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_photos');

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['tagline', 'highlights']);
        });
    }
};
