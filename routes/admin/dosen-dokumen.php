<?php

use App\Http\Controllers\Admin\DosenDokumenController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin', 'fakultas.scope'])->prefix('admin/dosen/{dosen}/dokumen')->name('admin.dosen.dokumen.')->group(function () {
    Route::get('/', [DosenDokumenController::class, 'index'])->name('index');
    Route::post('/', [DosenDokumenController::class, 'store'])->name('store');
    Route::delete('/{dokumen}', [DosenDokumenController::class, 'destroy'])->name('destroy');
});
