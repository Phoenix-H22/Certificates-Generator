<?php

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

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', [HomeController::class, 'index'])->name('home')->middleware('auth');

Auth::routes();

Route::get('/home', [HomeController::class, 'index'])->name('home')->middleware('auth');
Route::get('/cert', [HomeController::class, 'cert'])->name('certificate')->middleware('auth');
Route::get('/pdf', [PdfController::class, 'index'])->middleware('auth');
Route::get('/export-pdf', [PdfController::class, 'exportPdf'])->middleware('auth');
Route::post('/news', [HomeController::class, 'news']);


