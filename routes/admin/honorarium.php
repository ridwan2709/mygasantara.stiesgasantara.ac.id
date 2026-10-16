<?php

use App\Http\Controllers\Admin\HonorariumController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin', 'fakultas.scope'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/keuangan/honorarium', [HonorariumController::class, 'index'])->name('honorarium.index');
    Route::post('/keuangan/honorarium/kehadiran', [HonorariumController::class, 'storeKehadiran'])->name('honorarium.kehadiran.store');
    Route::patch('/keuangan/honorarium/kehadiran/{kehadiranMengajar}/verifikasi', [HonorariumController::class, 'verifyKehadiran'])->name('honorarium.kehadiran.verify');
    Route::post('/keuangan/honorarium/insentif', [HonorariumController::class, 'storeInsentif'])->name('honorarium.insentif.store');
    Route::post('/keuangan/honorarium/tarif', [HonorariumController::class, 'storeTarif'])->name('honorarium.tarif.store');
    Route::post('/keuangan/honorarium/generate', [HonorariumController::class, 'generate'])->name('honorarium.generate');
    Route::patch('/keuangan/honorarium/{honorarium}/bayar', [HonorariumController::class, 'markPaid'])->name('honorarium.pay');
    Route::get('/keuangan/honorarium/export/pdf', [HonorariumController::class, 'exportPdf'])->name('honorarium.export.pdf');
    Route::get('/keuangan/honorarium/export/excel', [HonorariumController::class, 'exportExcel'])->name('honorarium.export.excel');
});
