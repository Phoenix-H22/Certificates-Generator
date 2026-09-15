<?php

use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// v1 dashboard URL — everything lives in the Filament panel now.
Route::redirect('/home', '/admin');

/*
|--------------------------------------------------------------------------
| Public verification
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:verify')->group(function () {
    Route::get('/verify', [VerificationController::class, 'search'])->name('verify.search');
    Route::get('/verify/{identifier}', [VerificationController::class, 'show'])
        ->where('identifier', '[A-Za-z0-9\-]{1,64}')
        ->name('verify.show');
});
