<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reserva (pedido do hóspede). Pode incluir uma ou mais acomodações da mesma pousada.
     * check_out é o dia da saída (a última diária é a noite anterior).
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('status')->default('pending');
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedSmallInteger('guests');
            $table->string('guest_name');
            $table->string('guest_email')->nullable();
            $table->string('guest_phone');
            $table->text('notes')->nullable();
            $table->unsignedInteger('total');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'status', 'check_in']);
        });

        Schema::create('booking_accommodation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accommodation_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('subtotal');
            $table->timestamps();

            $table->unique(['booking_id', 'accommodation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_accommodation');
        Schema::dropIfExists('bookings');
    }
};
