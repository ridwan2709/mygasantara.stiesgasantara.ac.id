<?php

use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\JadwalKuliah;
use App\Models\KehadiranDosen;
use App\Models\KehadiranMengajar;
use App\Models\Kelas;
use App\Models\MataKuliah;
use App\Models\PengaturanGpsKehadiran;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatJadwalKehadiranAdmin(Dosen $dosen, string $namaMataKuliah): JadwalKuliah
{
    $mataKuliah = MataKuliah::create([
        'kode_mk' => fake()->unique()->numerify('ADM-####'),
        'nama_mk' => $namaMataKuliah,
        'sks' => 2,
        'semester' => 1,
    ]);
    $kelas = Kelas::create([
        'dosen_id' => $dosen->id,
        'mata_kuliah_id' => $mataKuliah->id,
        'nama_kelas' => 'A',
        'kapasitas' => 30,
    ]);

    return JadwalKuliah::create([
        'kelas_id' => $kelas->id,
        'hari' => now()->locale('id')->dayName,
        'jam_mulai' => '08:00',
        'jam_selesai' => '10:00',
        'ruangan' => 'A1',
    ]);
}

it('allows faculty admins to manage attendance for lecturers in their faculty only', function () {
    $this->withoutVite();

    $fakultas = Fakultas::factory()->create();
    $admin = User::factory()->create([
        'role' => 'admin_fakultas',
        'fakultas_id' => $fakultas->id,
    ]);
    $dosen = Dosen::factory()->create([
        'prodi_id' => Prodi::factory()->create(['fakultas_id' => $fakultas->id])->id,
    ]);
    $jadwal = buatJadwalKehadiranAdmin($dosen, 'Kehadiran Dosen Fakultas');
    $dosenLuarFakultas = Dosen::factory()->create();
    $jadwalLuarFakultas = buatJadwalKehadiranAdmin($dosenLuarFakultas, 'Kehadiran Dosen Luar');
    PengaturanGpsKehadiran::create([
        'latitude' => -6.2,
        'longitude' => 106.8166667,
        'radius_meter' => 100,
    ]);

    $this->actingAs($admin)
        ->get(route('dosen.kehadiran.index', ['dosen_id' => $dosen->id]))
        ->assertOk()
        ->assertSee($dosen->user->name)
        ->assertSee('Kehadiran Dosen Fakultas')
        ->assertDontSee('Kehadiran Dosen Luar');

    $this->actingAs($admin)
        ->post(route('dosen.kehadiran.store'), [
            'dosen_id' => $dosen->id,
            'jadwal_kuliah_id' => $jadwal->id,
            'status' => KehadiranDosen::STATUS_HADIR,
            'keterangan' => 'Dicatat oleh admin fakultas.',
            'latitude' => -6.2,
            'longitude' => 106.8166667,
        ])
        ->assertRedirect();

    $kehadiran = KehadiranDosen::where('dosen_id', $dosen->id)->firstOrFail();
    expect($kehadiran->dicatat_oleh)->toBe($admin->id);
    $this->assertDatabaseHas('kehadiran_mengajar', [
        'kehadiran_dosen_id' => $kehadiran->id,
        'dosen_id' => $dosen->id,
        'status_verifikasi' => KehadiranMengajar::STATUS_PENDING,
    ]);

    $this->actingAs($admin)
        ->get(route('dosen.kehadiran.index', ['dosen_id' => $dosen->id]))
        ->assertOk()
        ->assertSee($admin->name)
        ->assertSee('Dicatat oleh');

    $this->actingAs($admin)
        ->post(route('dosen.kehadiran.checkout', $kehadiran), [
            'latitude' => -6.2,
            'longitude' => 106.8166667,
        ])
        ->assertRedirect();

    expect($kehadiran->fresh()->dicatat_oleh)->toBe($admin->id)
        ->and($kehadiran->fresh()->jam_keluar)->not->toBeNull();

    $outsideFacultyAttendance = KehadiranDosen::create([
        'dosen_id' => $dosenLuarFakultas->id,
        'jadwal_kuliah_id' => $jadwalLuarFakultas->id,
        'tanggal' => now()->toDateString(),
        'jam_masuk' => '08:00',
        'status' => KehadiranDosen::STATUS_HADIR,
    ]);

    $this->actingAs($admin)
        ->post(route('dosen.kehadiran.checkout', $outsideFacultyAttendance), [
            'latitude' => -6.2,
            'longitude' => 106.8166667,
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('dosen.kehadiran.store'), [
            'dosen_id' => $dosenLuarFakultas->id,
            'jadwal_kuliah_id' => $jadwalLuarFakultas->id,
            'status' => KehadiranDosen::STATUS_IZIN,
        ])
        ->assertForbidden();
});

it('preserves lecturer access to their own attendance', function () {
    $dosen = Dosen::factory()->create();
    $jadwal = buatJadwalKehadiranAdmin($dosen, 'Kehadiran Dosen');

    $this->actingAs($dosen->user)
        ->post(route('dosen.kehadiran.store'), [
            'jadwal_kuliah_id' => $jadwal->id,
            'status' => KehadiranDosen::STATUS_IZIN,
        ])
        ->assertRedirect();

    expect(KehadiranDosen::firstOrFail()->dosen_id)->toBe($dosen->id);
});
