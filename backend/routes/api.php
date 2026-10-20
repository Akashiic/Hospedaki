<?php

use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\BookingRequestController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\PropertyController;
use Illuminate\Support\Facades\Route;

/*
| Site público: cada pousada é identificada pelo slug na URL.
*/
Route::prefix('properties/{property:slug}')->group(function () {
    Route::get('/', [PropertyController::class, 'show']);
    Route::get('availability', [PropertyController::class, 'availability'])->middleware('throttle:120,1');
    Route::get('hours', [PropertyController::class, 'hours'])->middleware('throttle:120,1');
    Route::post('quote', [BookingRequestController::class, 'quote'])->middleware('throttle:60,1');
    Route::post('booking-requests', [BookingRequestController::class, 'store'])->middleware('throttle:10,1');
});

// Fotos enviadas pelo painel (guardadas no banco).
Route::get('media/{media}', [MediaController::class, 'show'])->whereNumber('media');

/*
| Painel do dono da pousada (token Sanctum). Cada dono só enxerga a própria pousada.
*/
Route::prefix('admin')->group(function () {
    Route::post('login', [Admin\AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('demo-login', [Admin\AuthController::class, 'demoLogin'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [Admin\AuthController::class, 'me']);
        Route::post('logout', [Admin\AuthController::class, 'logout']);
        Route::get('bookings', [Admin\BookingController::class, 'index']);
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
        Route::post('accommodations/{accommodation}/photos', [Admin\AccommodationController::class, 'storePhoto'])->middleware('throttle:30,1');
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
        Route::post('site/images/{kind}', [Admin\SiteController::class, 'storeImage'])->middleware('throttle:30,1');
        Route::delete('site/images/{kind}', [Admin\SiteController::class, 'destroyImage']);
    });
});
