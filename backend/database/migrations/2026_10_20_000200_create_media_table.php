<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fotos enviadas pelo painel, guardadas no próprio banco: o Render (plano gratuito) apaga os
     * arquivos do disco a cada deploy. Já chegam reduzidas (no máximo 1600 px, JPEG).
     */
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('mime', 40);
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->unsignedInteger('size');
            // Base64: bytes crus num bytea do Postgres pedem binding especial no PDO; texto funciona em qualquer banco.
            $table->longText('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
