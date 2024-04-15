<?php

use App\Http\Controllers\GenerateController;
use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\HomeController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
require __DIR__.'/auth.php';
Route::get('/', function () {
    return view('welcome');
});

Route::get('/home', [HomeController::class, 'index'])->name('home')->middleware('auth');


Route::get('/home', [HomeController::class, 'index'])->name('home')->middleware('auth');
Route::get('/cert', [HomeController::class, 'cert'])->name('certificate')->middleware('auth');
Route::get('/pdf', [PdfController::class, 'index'])->middleware('auth');
Route::get('/export-pdf', [PdfController::class, 'exportPdf'])->middleware('auth');
Route::post('/news', [HomeController::class, 'news']);
Route::post('/upload-template', [TemplateController::class, 'upload_template'])->middleware('auth')->name('upload-template');
Route::get('/download-template', [TemplateController::class, 'download_template'])->middleware('auth')->name('download-template');
Route::get('/download-sheet', [GenerateController::class, 'download_sample'])->middleware('auth')->name('download-sheet');
Route::post('/upload-sheet', [GenerateController::class, 'upload_sheet'])->middleware('auth')->name('upload-sheet');


