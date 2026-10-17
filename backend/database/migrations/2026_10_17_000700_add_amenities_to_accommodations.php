<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accommodations', function (Blueprint $table) {
            // Lista curta de comodidades exibida na página da acomodação (ex.: "Piscina", "Hidromassagem").
            $table->json('amenities')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('accommodations', function (Blueprint $table) {
            $table->dropColumn('amenities');
        });
    }
};
