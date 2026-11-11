<?php

use App\Http\Controllers\Mahasiswa\PresensiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:mahasiswa'])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
    Route::get('/presensi', [PresensiController::class, 'index'])->name('presensi.index');
    Route::get('/presensi/{kelas}', [PresensiController::class, 'show'])->name('presensi.show');
    Route::post('/presensi/pertemuan/{pertemuan}', [PresensiController::class, 'store'])->name('presensi.store');
});
