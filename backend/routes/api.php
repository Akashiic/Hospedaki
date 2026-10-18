<?php

use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\BookingRequestController;
use App\Http\Controllers\Api\PropertyController;
use Illuminate\Support\Facades\Route;

/*
| Site público: cada pousada é identificada pelo slug na URL.
*/
Route::prefix('properties/{property:slug}')->group(function () {
    Route::get('/', [PropertyController::class, 'show']);
    Route::get('availability', [PropertyController::class, 'availability'])->middleware('throttle:120,1');
    Route::post('quote', [BookingRequestController::class, 'quote'])->middleware('throttle:60,1');
    Route::post('booking-requests', [BookingRequestController::class, 'store'])->middleware('throttle:10,1');
});

/*
| Painel do dono da pousada (token Sanctum). Cada dono só enxerga a própria pousada.
*/
Route::prefix('admin')->group(function () {
    Route::post('login', [Admin\AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [Admin\AuthController::class, 'me']);
        Route::post('logout', [Admin\AuthController::class, 'logout']);
        Route::get('bookings', [Admin\BookingController::class, 'index']);
        Route::post('bookings/{booking}/confirm', [Admin\BookingController::class, 'confirm']);
        Route::post('bookings/{booking}/decline', [Admin\BookingController::class, 'decline']);
        Route::get('blocked-dates', [Admin\BlockedDateController::class, 'index']);
        Route::post('blocked-dates', [Admin\BlockedDateController::class, 'store']);
        Route::delete('blocked-dates/{blockedDate}', [Admin\BlockedDateController::class, 'destroy']);
    });
});
