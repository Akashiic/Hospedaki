<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ficha da acomodação para o Google (Schema.org): tipo, quartos, banheiros, área, camas e se
     * aceita pets ou fumantes. Tudo opcional; o site só publica o que estiver preenchido.
     */
    public function up(): void
    {
        Schema::table('accommodations', function (Blueprint $table) {
            $table->json('details')->nullable()->after('amenities');
        });
    }

    public function down(): void
    {
        Schema::table('accommodations', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }
};
