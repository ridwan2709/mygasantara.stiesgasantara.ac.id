<?php

use App\Http\Controllers\Mahasiswa\TagihanController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:mahasiswa'])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
    Route::get('/tagihan', [TagihanController::class, 'index'])->name('tagihan.index');
});
