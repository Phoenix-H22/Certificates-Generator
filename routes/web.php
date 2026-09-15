<?php

use App\Http\Controllers\AdminFileController;
use App\Http\Controllers\PublicDownloadController;
use App\Http\Controllers\TemplatePreviewController;
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
        ->where('identifier', '[A-Za-z0-9 \-]{1,64}')
        ->name('verify.show');
});

/*
|--------------------------------------------------------------------------
| Admin-only template preview (HTML / PDF / PNG with sample data)
|--------------------------------------------------------------------------
*/
Route::get('/admin-preview/templates/{template}', TemplatePreviewController::class)
    ->middleware(['web', 'auth', 'can:admin'])
    ->name('templates.preview');

/*
|--------------------------------------------------------------------------
| Files: signed public download + authenticated admin downloads
|--------------------------------------------------------------------------
*/
Route::get('/c/{certificate}', PublicDownloadController::class)
    ->middleware(['signed', 'throttle:downloads'])
    ->name('certificates.public-download');

Route::middleware(['web', 'auth', 'can:admin'])->prefix('files')->name('files.')->group(function () {
    Route::get('/certificates/{certificate}/pdf', [AdminFileController::class, 'certificatePdf'])->name('certificate.pdf');
    Route::get('/batches/{batch}/zip', [AdminFileController::class, 'batchZip'])->name('batch.zip');
    Route::get('/batches/{batch}/errors.xlsx', [AdminFileController::class, 'batchErrors'])->name('batch.errors');
    Route::get('/batches/{batch}/source', [AdminFileController::class, 'batchSource'])->name('batch.source');
});
