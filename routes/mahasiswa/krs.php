<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Mahasiswa\KrsController;

Route::middleware(['auth', 'role:mahasiswa'])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
    Route::get('/krs', [KrsController::class, 'index'])->name('krs.index');
});