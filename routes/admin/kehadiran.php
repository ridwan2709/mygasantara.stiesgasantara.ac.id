<?php

use App\Http\Controllers\Admin\KehadiranDosenController;
use App\Models\PengaturanGpsKehadiran;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin', 'fakultas.scope'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/kehadiran-dosen', [KehadiranDosenController::class, 'index'])->name('kehadiran-dosen.index');
    Route::get('/kehadiran-dosen/export/pdf', [KehadiranDosenController::class, 'exportPdf'])->name('kehadiran-dosen.export.pdf');
    Route::get('/kehadiran-dosen/{dosen}', [KehadiranDosenController::class, 'show'])->name('kehadiran-dosen.show');

});

Route::middleware(['auth', 'role:superadmin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/pengaturan-gps-kehadiran', function () {
        $pengaturanGps = PengaturanGpsKehadiran::query()->first();

        return view('admin.kehadiran-dosen.pengaturan-gps', compact('pengaturanGps'));
    })->name('kehadiran-gps.edit');

    Route::put('/pengaturan-gps-kehadiran', [KehadiranDosenController::class, 'updateGpsSettings'])
        ->name('kehadiran-gps.update');
});
