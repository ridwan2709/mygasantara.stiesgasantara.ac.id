<?php

use App\Models\Dosen;
use App\Models\KehadiranDosen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('shows employee attendance summaries including lecturers who are program administrators', function () {
    $this->withoutVite();

    $admin = User::factory()->create(['role' => 'superadmin']);
    $staff = User::factory()->create(['name' => 'Pegawai Pengujian', 'role' => 'staff']);
    KehadiranDosen::create([
        'user_id' => $staff->id,
        'dosen_id' => null,
        'tanggal' => now()->toDateString(),
        'jam_masuk' => '08:00:00',
        'status' => KehadiranDosen::STATUS_HADIR,
    ]);

    $adminProdiDosen = Dosen::factory()->create();
    $adminProdiDosen->user->assignRole(Role::firstOrCreate([
        'name' => 'admin_prodi',
        'guard_name' => 'web',
    ]));
    KehadiranDosen::create([
        'dosen_id' => $adminProdiDosen->id,
        'user_id' => null,
        'jadwal_kuliah_id' => null,
        'tanggal' => now()->toDateString(),
        'jam_masuk' => '09:00:00',
        'status' => KehadiranDosen::STATUS_HADIR,
    ]);

    $regularLecturer = Dosen::factory()->create();
    KehadiranDosen::create([
        'dosen_id' => $regularLecturer->id,
        'tanggal' => now()->toDateString(),
        'jam_masuk' => '10:00:00',
        'status' => KehadiranDosen::STATUS_HADIR,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.kehadiran-dosen.index'))
        ->assertOk()
        ->assertSee('Rekap Kehadiran Pegawai')
        ->assertSee('Pegawai Pengujian')
        ->assertSee('Dosen / Admin Prodi')
        ->assertSee($adminProdiDosen->user->name);

    $employeeSummary = strstr($response->getContent(), 'id="rekap-kehadiran-pegawai"');
    $employeeSummary = strstr($employeeSummary, '<!-- Detail -->', true);
    expect($employeeSummary)->not->toContain($regularLecturer->user->name);
});
