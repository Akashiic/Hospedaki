<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Horários nas reservas:
     *  - acomodação por diária (padrão) com check-in e check-out próprios (vazio = os da pousada);
     *  - acomodação por hora (day use, eventos), com preço por hora, mínimo de horas e horário de funcionamento;
     *  - reserva com o horário previsto de chegada do hóspede, ou o início e o fim, quando é por hora.
     */
    public function up(): void
    {
        Schema::table('accommodations', function (Blueprint $table) {
            $table->string('booking_mode', 10)->default('nightly')->after('shared_unit');
            $table->time('check_in_time')->nullable()->after('min_nights');
            $table->time('check_out_time')->nullable()->after('check_in_time');
            $table->unsignedInteger('hourly_price')->nullable()->after('check_out_time');
            $table->unsignedTinyInteger('min_hours')->default(1)->after('hourly_price');
            $table->time('opens_at')->nullable()->after('min_hours');
            $table->time('closes_at')->nullable()->after('opens_at');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->time('arrival_time')->nullable()->after('check_out');
            $table->time('start_time')->nullable()->after('arrival_time');
            $table->time('end_time')->nullable()->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['arrival_time', 'start_time', 'end_time']);
        });

        Schema::table('accommodations', function (Blueprint $table) {
            $table->dropColumn(['booking_mode', 'check_in_time', 'check_out_time', 'hourly_price', 'min_hours', 'opens_at', 'closes_at']);
        });
    }
};
