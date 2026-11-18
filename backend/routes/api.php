<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'ok']);
Route::get('/ready', function () {
    DB::select('SELECT 1');

    return ['status' => 'ready'];
});
Route::middleware('throttle:120,1,public-read:')->group(function () {
    Route::get('/public/catalog', [PublicController::class, 'catalog']);
    Route::get('/availability', [PublicController::class, 'availability']);
});
Route::post('/appointments', [PublicController::class, 'book'])->middleware('throttle:10,1,book:');
Route::post('/registrations', [PublicController::class, 'register'])->middleware('throttle:10,1,register:');
Route::post('/contact', [PublicController::class, 'contact'])->middleware('throttle:5,1,contact:');
// Web middleware guarantees guest/admin sessions and CSRF, independent of Origin headers.
Route::middleware('web')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1,login:');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:3,1,forgot:');
    Route::post('/auth/reset-password', [AuthController::class, 'reset'])->middleware('throttle:5,1,reset:');
    Route::post('/guest/exchange', [PublicController::class, 'exchange'])->middleware('throttle:10,1,exchange:');
    Route::post('/guest/recover', [PublicController::class, 'recover'])->middleware('throttle:3,1,recover:');
    Route::post('/guest/appointments/{reference}/cancel', [PublicController::class, 'cancel'])->middleware('throttle:10,1,cancel:');
    Route::post('/guest/appointments/{reference}/reschedule', [PublicController::class, 'reschedule'])->middleware('throttle:10,1,reschedule:');
    Route::post('/guest/registrations/{reference}/cancel', [PublicController::class, 'cancelRegistration'])->middleware('throttle:10,1,cancelRegistration:');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/admin/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword'])->middleware('throttle:5,1,password-change:');
        Route::get('/admin/calendar', [CalendarController::class, 'index']);
        Route::get('/admin/availability', [CalendarController::class, 'availability']);
        Route::get('/admin/appointments/{id}/history', [CalendarController::class, 'history']);
        Route::get('/admin/statistics', [AdminController::class, 'statistics']);
        Route::post('/admin/uploads', [AdminController::class, 'upload'])->middleware('throttle:20,1,upload:');
        Route::get('/admin/{resource}', [AdminController::class, 'index']);
        Route::post('/admin/{resource}', [AdminController::class, 'store']);
        Route::put('/admin/{resource}/{id}', [AdminController::class, 'update']);
        Route::delete('/admin/{resource}/{id}', [AdminController::class, 'destroy']);
    });
});
