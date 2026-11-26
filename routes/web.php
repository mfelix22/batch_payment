<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\paymentController;
use App\Http\Controllers\BcaController;
use App\Http\Controllers\BniController;
use App\Http\Controllers\BriController;
use App\Http\Controllers\BsiController;
use App\Http\Controllers\BtnController;
use App\Http\Controllers\MandiriController;
use App\Http\Controllers\SumselBabelController;

// Batch Payment Routes (Original)
Route::get('/', [paymentController::class, 'index'])->name('home');
Route::post('/payment/upload', [paymentController::class, 'upload']);
Route::post('/payment/export', [PaymentController::class, 'export'])->name('payment.export');

// Bank Import Routes (Merged from import_bank)
//BCA
Route::resource('bca', BcaController::class);
Route::post('/bca-save-export', [BcaController::class, 'saveExport'])->name('bca.saveExport');

//Mandiri
Route::resource('mandiri', MandiriController::class);
Route::post('/mandiri-save-export', [MandiriController::class, 'saveExport'])->name('mandiri.saveExport');

//BSI
Route::resource('bsi', BsiController::class);
Route::post('/bsi-save-export', [BsiController::class, 'saveExport'])->name('bsi.saveExport');

//BRI
Route::resource('bri', BriController::class);
Route::post('/bri-save-export', [BriController::class, 'saveExport'])->name('bri.saveExport');

//BTN
Route::resource('btn', BtnController::class);
Route::post('/btn-save-export', [BtnController::class, 'saveExport'])->name('btn.saveExport');

//BNI
Route::resource('bni', BniController::class);
Route::post('/bni-save-export', [BniController::class, 'saveExport'])->name('bni.saveExport');

//Sumsel Babel
Route::resource('sumselbabel', SumselBabelController::class);
Route::post('/sumselbabel-save-export', [SumselBabelController::class, 'saveExport'])->name('sumselbabel.saveExport');
