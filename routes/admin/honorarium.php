<?php

use App\Http\Controllers\Admin\HonorariumController;
use App\Http\Controllers\Admin\TarifHonorPegawaiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin,bendahara', 'fakultas.scope'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/keuangan/honorarium', [HonorariumController::class, 'index'])->name('honorarium.index');
    Route::get('/keuangan/pengaturan-tarif', [TarifHonorPegawaiController::class, 'index'])->name('pengaturan-tarif.index');
    Route::post('/keuangan/pengaturan-tarif/dosen', [HonorariumController::class, 'storeTarif'])->name('pengaturan-tarif.dosen.store');
    Route::post('/keuangan/pengaturan-tarif/pegawai', [TarifHonorPegawaiController::class, 'store'])->name('pengaturan-tarif.pegawai.store');
    Route::patch('/keuangan/honorarium/{honorarium}/bayar', [HonorariumController::class, 'markPaid'])->name('honorarium.pay');
    Route::get('/keuangan/honorarium/export/pdf', [HonorariumController::class, 'exportPdf'])->name('honorarium.export.pdf');
    Route::get('/keuangan/honorarium/export/excel', [HonorariumController::class, 'exportExcel'])->name('honorarium.export.excel');
});

Route::middleware(['auth', 'role:admin,bendahara', 'fakultas.scope'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/keuangan/honorarium/kehadiran', [HonorariumController::class, 'storeKehadiran'])->name('honorarium.kehadiran.store');
    Route::patch('/keuangan/honorarium/kehadiran/{kehadiranMengajar}/verifikasi', [HonorariumController::class, 'verifyKehadiran'])->name('honorarium.kehadiran.verify');
    Route::post('/keuangan/honorarium/insentif', [HonorariumController::class, 'storeInsentif'])->name('honorarium.insentif.store');
    Route::post('/keuangan/honorarium/generate', [HonorariumController::class, 'generate'])->name('honorarium.generate');
});

Route::middleware(['auth', 'role:superadmin,bendahara'])->prefix('admin')->name('admin.')->group(function () {
    Route::patch('/keuangan/honorarium/pegawai/{user}/bayar', [HonorariumController::class, 'markPegawaiPaid'])->name('honorarium.pegawai.pay');
});