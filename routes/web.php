<?php

use App\Http\Controllers\LegalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Controller, not closures: route:cache in production can't serialize a
// closure that returns another one.
Route::get('privacy', [LegalController::class, 'privacy'])->name('privacy');
Route::get('support', [LegalController::class, 'support'])->name('support');
