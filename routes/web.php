<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
 * Public pages App Store Connect links to. Slovak or English by ?lang=,
 * otherwise by the browser's language.
 */
$legal = fn (string $view) => function (Request $request) use ($view) {
    $lang = in_array($request->query('lang'), ['sk', 'en'], true)
        ? $request->query('lang')
        : ($request->getPreferredLanguage(['sk', 'en']) ?? 'en');

    return view("legal.{$view}", [
        'lang' => $lang,
        'contact' => config('services.lumen.contact_email'),
    ]);
};

Route::get('privacy', $legal('privacy'))->name('privacy');
Route::get('support', $legal('support'))->name('support');
