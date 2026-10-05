<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\KrsManagementController;
use App\Http\Controllers\Admin\KrsApprovalController;

Route::middleware(['auth', 'role:admin', 'fakultas.scope'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/krs/create', [KrsManagementController::class, 'create'])->name('krs.create');
    Route::post('/krs', [KrsManagementController::class, 'store'])->name('krs.store');
    Route::get('/krs-approval', [KrsApprovalController::class, 'index'])->name('krs-approval.index');
    Route::get('/krs-approval/{krs}', [KrsApprovalController::class, 'show'])->name('krs-approval.show');
});
