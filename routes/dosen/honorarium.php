<?php

use App\Http\Controllers\Dosen\HonorariumController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:dosen'])->prefix('dosen')->name('dosen.')->group(function () {
    Route::get('/honorarium', [HonorariumController::class, 'index'])->name('honorarium.index');
    Route::get('/honorarium/{honorarium}/slip', [HonorariumController::class, 'slip'])->name('honorarium.slip');
});
