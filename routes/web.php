<?php

use App\Http\Controllers\Bendahara\DashboardController as BendaharaDashboardController;
use App\Http\Controllers\Pegawai\KehadiranController as PegawaiKehadiranController;
use App\Http\Controllers\Staff\DashboardController as StaffDashboardController;
use Illuminate\Support\Facades\Route;

// Redirect root to login page
Route::get('/', fn () => redirect()->route('login'));

// Redirect generic dashboard to role-specific dashboard
Route::get('/dashboard', function () {
    $routes = [
        'superadmin' => 'admin.dashboard',
        'admin_fakultas' => 'admin.dashboard',
        'dosen' => 'dosen.dashboard',
        'mahasiswa' => 'mahasiswa.dashboard',
        'bendahara' => 'bendahara.dashboard',
        'staff' => 'staff.dashboard',
    ];

    $role = auth()->user()->role;

    return redirect()->route($routes[$role] ?? 'login');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'role:bendahara'])->prefix('bendahara')->name('bendahara.')->group(function () {
    Route::get('/dashboard', [BendaharaDashboardController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'role:staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/dashboard', [StaffDashboardController::class, 'index'])->name('dashboard');
    Route::get('/khs', [\App\Http\Controllers\Staff\KhsController::class, 'index'])->name('khs.index');
});

Route::middleware(['auth', 'can.absen'])->prefix('pegawai')->name('pegawai.')->group(function () {
    Route::get('/kehadiran', [PegawaiKehadiranController::class, 'index'])->name('kehadiran.index');
    Route::post('/kehadiran/masuk', [PegawaiKehadiranController::class, 'masuk'])->name('kehadiran.masuk');
    Route::post('/kehadiran/keluar', [PegawaiKehadiranController::class, 'keluar'])->name('kehadiran.keluar');
});

require __DIR__.'/auth.php';
require __DIR__.'/health.php';
require __DIR__.'/notification.php';
require __DIR__.'/profile.php';
require __DIR__.'/admin/index.php';
require __DIR__.'/dosen/index.php';
require __DIR__.'/mahasiswa/index.php';
