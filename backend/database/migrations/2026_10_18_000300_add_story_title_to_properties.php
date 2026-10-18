<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Título da seção "Um dia aqui" na home. Cada pousada tem o seu: nem toda tem fogueira.
            $table->string('story_title')->nullable()->after('story');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('story_title');
        });
    }
};
