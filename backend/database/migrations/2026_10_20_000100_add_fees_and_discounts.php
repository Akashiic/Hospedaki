<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accommodations', function (Blueprint $table) {
            // Cobrada uma vez por estadia, em centavos.
            $table->unsignedInteger('cleaning_fee')->nullable()->after('extra_guest_price');
            // Desconto (%) sobre as diárias em estadias de 7 noites ou mais, e de 28 ou mais.
            $table->unsignedTinyInteger('weekly_discount')->nullable()->after('cleaning_fee');
            $table->unsignedTinyInteger('monthly_discount')->nullable()->after('weekly_discount');
        });

        Schema::table('seasonal_prices', function (Blueprint $table) {
            // Mínimo de noites para reservas que pegam a temporada (ex.: 3 no réveillon).
            $table->unsignedSmallInteger('min_nights')->nullable()->after('weekend_price');
        });
    }

    public function down(): void
    {
        Schema::table('accommodations', function (Blueprint $table) {
            $table->dropColumn(['cleaning_fee', 'weekly_discount', 'monthly_discount']);
        });

        Schema::table('seasonal_prices', function (Blueprint $table) {
            $table->dropColumn('min_nights');
        });
    }
};
