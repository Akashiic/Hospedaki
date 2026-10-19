<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accommodations', function (Blueprint $table) {
            // Acomodações com o mesmo valor dividem o mesmo imóvel e não podem ser reservadas ao
            // mesmo tempo: o Espaço Bela Vista inclui o apartamento que também é alugado sozinho.
            $table->string('shared_unit')->nullable()->after('slug');
            // Para quem a pousada indica esta acomodação na capa ("casal" ou "familia"); vazio = pela capacidade.
            $table->string('best_for', 20)->nullable()->after('shared_unit');
        });

        Schema::table('properties', function (Blueprint $table) {
            // Serviços opcionais (café da manhã, decoração, churrasqueiro...): [{title, text}].
            $table->json('extras')->nullable()->after('story_title');
        });
    }

    public function down(): void
    {
        Schema::table('accommodations', function (Blueprint $table) {
            $table->dropColumn(['shared_unit', 'best_for']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('extras');
        });
    }
};
