<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accommodations', function (Blueprint $table) {
            // Pessoas incluídas no valor da diária e acréscimo por pessoa a mais, por diária, em centavos.
            // Nulo = não informado: sem included_guests a diária vale para a capacidade toda;
            // sem extra_guest_price o acréscimo existe, mas o valor fica a combinar com a pousada.
            $table->unsignedSmallInteger('included_guests')->nullable()->after('max_guests');
            $table->unsignedInteger('extra_guest_price')->nullable()->after('weekend_price');
        });

        Schema::table('bookings', function (Blueprint $table) {
            // Pessoas a mais cujo acréscimo não está no total: o dono vê que ainda falta combinar esse valor.
            $table->unsignedSmallInteger('extra_guests_unpriced')->default(0)->after('guests');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('extra_guests_unpriced');
        });

        Schema::table('accommodations', function (Blueprint $table) {
            $table->dropColumn(['included_guests', 'extra_guest_price']);
        });
    }
};
