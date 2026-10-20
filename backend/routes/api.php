<?php

use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\BookingRequestController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\PropertyController;
use Illuminate\Support\Facades\Route;

/*
| Site público: cada pousada é identificada pelo slug na URL.
|
| O terceiro parâmetro do throttle dá a cada rota o seu contador: sem ele, todas as rotas com
| throttle dividem um só por IP, e quem passeava pelo calendário (uma consulta por mês) levava
| "muitas tentativas" ao enviar o pedido.
*/
Route::prefix('properties/{property:slug}')->group(function () {
    Route::get('/', [PropertyController::class, 'show']);
    Route::get('availability', [PropertyController::class, 'availability'])->middleware('throttle:120,1,availability');
    Route::get('hours', [PropertyController::class, 'hours'])->middleware('throttle:120,1,hours');
    Route::post('quote', [BookingRequestController::class, 'quote'])->middleware('throttle:60,1,quote');
    Route::post('booking-requests', [BookingRequestController::class, 'store'])->middleware('throttle:10,1,booking');
});

/*
| Checkout: o hóspede paga a reserva pelo link com o token secreto dela.
*/
Route::prefix('checkout/{token}')->group(function () {
    Route::get('/', [CheckoutController::class, 'show'])->middleware('throttle:60,1,checkout');
    Route::post('pix', [CheckoutController::class, 'pix'])->middleware('throttle:10,1,checkout-pix');
    Route::post('card', [CheckoutController::class, 'card'])->middleware('throttle:10,1,checkout-card');
    Route::post('simulate', [CheckoutController::class, 'simulate'])->middleware('throttle:10,1,checkout-simulate');
});

// Avisos do Mercado Pago e da Stripe quando um pagamento muda.
Route::post('webhooks/{provider}/{property:slug}', PaymentWebhookController::class)->middleware('throttle:120,1,webhook');

// Fotos enviadas pelo painel (guardadas no banco).
Route::get('media/{media}', [MediaController::class, 'show'])->whereNumber('media');

/*
| Painel do dono da pousada (token Sanctum). Cada dono só enxerga a própria pousada.
*/
Route::prefix('admin')->group(function () {
    Route::post('login', [Admin\AuthController::class, 'login'])->middleware('throttle:10,1,login');
    Route::post('demo-login', [Admin\AuthController::class, 'demoLogin'])->middleware('throttle:10,1,demo-login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [Admin\AuthController::class, 'me']);
        Route::post('logout', [Admin\AuthController::class, 'logout']);
        Route::get('bookings', [Admin\BookingController::class, 'index']);
        Route::get('stats', Admin\StatsController::class);
        Route::post('bookings/{booking}/confirm', [Admin\BookingController::class, 'confirm']);
        Route::post('bookings/{booking}/decline', [Admin\BookingController::class, 'decline']);
        Route::get('blocked-dates', [Admin\BlockedDateController::class, 'index']);
        Route::post('blocked-dates', [Admin\BlockedDateController::class, 'store']);
        Route::delete('blocked-dates/{blockedDate}', [Admin\BlockedDateController::class, 'destroy']);
        Route::get('accommodations', [Admin\AccommodationController::class, 'index']);
        Route::post('accommodations', [Admin\AccommodationController::class, 'store']);
        Route::put('accommodations/order', [Admin\AccommodationController::class, 'reorder']);
        Route::put('accommodations/{accommodation}', [Admin\AccommodationController::class, 'update']);
        Route::delete('accommodations/{accommodation}', [Admin\AccommodationController::class, 'destroy']);
        Route::post('accommodations/{accommodation}/photos', [Admin\AccommodationController::class, 'storePhoto'])->middleware('throttle:30,1,photo-upload');
        Route::put('accommodations/{accommodation}/photos', [Admin\AccommodationController::class, 'updatePhotos']);
        Route::delete('accommodations/{accommodation}/photos/{photo}', [Admin\AccommodationController::class, 'destroyPhoto']);
        Route::post('accommodations/{accommodation}/seasons', [Admin\AccommodationController::class, 'storeSeason']);
        Route::put('accommodations/{accommodation}/seasons/{season}', [Admin\AccommodationController::class, 'updateSeason']);
        Route::delete('accommodations/{accommodation}/seasons/{season}', [Admin\AccommodationController::class, 'destroySeason']);
        Route::get('layout', [Admin\LayoutController::class, 'show']);
        Route::put('layout', [Admin\LayoutController::class, 'update']);
        Route::get('site', [Admin\SiteController::class, 'show']);
        Route::put('site/identity', [Admin\SiteController::class, 'updateIdentity']);
        Route::put('site/seo', [Admin\SiteController::class, 'updateSeo']);
        Route::put('site/pages/{page}', [Admin\SiteController::class, 'updatePage']);
        Route::get('payments', [Admin\PaymentController::class, 'show']);
        Route::put('payments/settings', [Admin\PaymentController::class, 'updateSettings']);
        Route::put('payments/gateways/{provider}', [Admin\PaymentController::class, 'updateGateway'])->middleware('throttle:20,1,gateway');
        Route::delete('payments/gateways/{provider}', [Admin\PaymentController::class, 'destroyGateway']);
        Route::post('site/images/{kind}', [Admin\SiteController::class, 'storeImage'])->middleware('throttle:30,1,site-image');
        Route::delete('site/images/{kind}', [Admin\SiteController::class, 'destroyImage']);
    });
});
