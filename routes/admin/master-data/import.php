<?php

use App\Http\Controllers\Admin\MasterDataImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin', 'fakultas.scope'])->prefix('admin/master-data')->name('admin.master-data.')->group(function () {
    Route::get('/{type}/template', [MasterDataImportController::class, 'template'])->name('template');
    Route::post('/{type}/import', [MasterDataImportController::class, 'import'])->name('import');
});
