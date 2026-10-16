<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EntryController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TemplateController;
use Illuminate\Support\Facades\Route;

/*
 * Lumen API.
 *
 * Everything except signing in sits behind a Sanctum token. There is no
 * password registration route — password accounts are created with
 * `php artisan lumen:user`; Sign in with Apple creates its own on first use.
 */

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('auth/apple', [AuthController::class, 'apple'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::delete('user', [AuthController::class, 'destroy'])->middleware('throttle:3,1');

    // Local-first sync — what the app actually uses day to day.
    Route::get('sync', [SyncController::class, 'pull']);
    Route::post('sync', [SyncController::class, 'push']);

    // Direct REST, handy for debugging and for anything that is not the app.
    Route::apiResource('entries', EntryController::class);
    Route::apiResource('templates', TemplateController::class)->except('show');

    // Media: sign → upload straight to R2 → confirm.
    Route::post('media/presign', [MediaController::class, 'presign']);
    Route::post('media/urls', [MediaController::class, 'urls']);
    Route::post('media', [MediaController::class, 'store']);
    Route::delete('media/{medium}', [MediaController::class, 'destroy']);
});
