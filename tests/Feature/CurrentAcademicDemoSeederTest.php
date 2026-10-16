<?php

use App\Models\Dosen;
use App\Models\Honorarium;
use App\Models\JadwalKuliah;
use App\Models\KehadiranMengajar;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Presensi;
use App\Models\TahunAkademik;
use Database\Seeders\CurrentAcademicDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds current connected academic and honorarium demo data idempotently', function () {
    $this->seed(CurrentAcademicDemoSeeder::class);

    $activeTerm = TahunAkademik::where('is_active', true)->firstOrFail();
    $studentsCount = Mahasiswa::whereHas('user', fn ($query) => $query->where('email', 'like', 'demo.mahasiswa%@siakad.test'))->count();

    expect($studentsCount)->toBe(12)
        ->and(Dosen::whereHas('user', fn ($query) => $query->where('email', 'like', 'demo.dosen%@siakad.test'))->count())->toBe(4)
        ->and(Kelas::where('tahun_akademik_id', $activeTerm->id)->count())->toBe(6)
        ->and(JadwalKuliah::whereHas('kelas', fn ($query) => $query->where('tahun_akademik_id', $activeTerm->id))->count())->toBe(6)
        ->and(Krs::where('tahun_akademik_id', $activeTerm->id)->where('status', 'approved')->count())->toBe(10)
        ->and(Krs::where('tahun_akademik_id', $activeTerm->id)->where('status', 'pending')->count())->toBe(2)
        ->and(Presensi::count())->toBeGreaterThan(0)
        ->and(KehadiranMengajar::where('status_verifikasi', KehadiranMengajar::STATUS_PENDING)->count())->toBeGreaterThan(0)
        ->and(KehadiranMengajar::where('status_verifikasi', KehadiranMengajar::STATUS_APPROVED)->count())->toBeGreaterThan(0)
        ->and(Honorarium::where('tahun', today()->year)->where('bulan', today()->month)->count())->toBe(4)
        ->and(Honorarium::where('status_pembayaran', Honorarium::STATUS_PAID)->count())->toBeGreaterThan(0);

    $this->seed(CurrentAcademicDemoSeeder::class);

    expect(Mahasiswa::whereHas('user', fn ($query) => $query->where('email', 'like', 'demo.mahasiswa%@siakad.test'))->count())->toBe(12)
        ->and(Dosen::whereHas('user', fn ($query) => $query->where('email', 'like', 'demo.dosen%@siakad.test'))->count())->toBe(4)
        ->and(Kelas::where('tahun_akademik_id', $activeTerm->id)->count())->toBe(6)
        ->and(Honorarium::where('tahun', today()->year)->where('bulan', today()->month)->count())->toBe(4);
});
