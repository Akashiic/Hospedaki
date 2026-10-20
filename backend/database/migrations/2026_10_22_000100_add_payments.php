<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pagamento online pelo Mercado Pago ou pela Stripe:
     *  - pousada com as chaves de cada gateway (criptografadas) e qual cobra o Pix e qual cobra o cartão;
     *  - acomodação com as formas aceitas, o desconto no Pix e o prazo da promoção (com timer que pode recomeçar);
     *  - reserva com o prazo para pagar, o link do checkout e quando foi paga;
     *  - cada tentativa de pagamento (um Pix gerado, um cartão passado) numa linha de payments.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->text('payment_credentials')->nullable()->after('settings');
        });

        Schema::table('accommodations', function (Blueprint $table) {
            $table->boolean('accepts_pix')->default(true)->after('closes_at');
            $table->boolean('accepts_card')->default(true)->after('accepts_pix');
            $table->unsignedTinyInteger('pix_discount')->default(0)->after('accepts_card');
            $table->timestamp('promo_ends_at')->nullable()->after('pix_discount');
            $table->boolean('promo_loop')->default(false)->after('promo_ends_at');
            $table->unsignedSmallInteger('promo_loop_hours')->default(24)->after('promo_loop');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('checkout_token', 40)->nullable()->unique()->after('code');
            $table->timestamp('payment_due_at')->nullable()->after('cancelled_at');
            $table->timestamp('paid_at')->nullable()->after('payment_due_at');
            $table->string('payment_method', 10)->nullable()->after('paid_at');
            $table->unsignedInteger('pix_discount')->default(0)->after('payment_method');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('method', 10);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('amount');
            $table->unsignedInteger('discount')->default(0);
            $table->string('external_id')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_id']);
            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['checkout_token']);
            $table->dropColumn(['checkout_token', 'payment_due_at', 'paid_at', 'payment_method', 'pix_discount']);
        });

        Schema::table('accommodations', function (Blueprint $table) {
            $table->dropColumn(['accepts_pix', 'accepts_card', 'pix_discount', 'promo_ends_at', 'promo_loop', 'promo_loop_hours']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('payment_credentials');
        });
    }
};
