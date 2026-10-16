<?php

use App\Models\Dosen;
use App\Models\KehadiranMengajar;
use App\Models\JadwalKuliah;
use App\Models\KehadiranDosen;
use App\Models\Kelas;
use App\Models\MataKuliah;
use App\Models\PengaturanGpsKehadiran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatJadwalDosen(Dosen $dosen): JadwalKuliah
{
    $mataKuliah = MataKuliah::create([
        'kode_mk' => 'GPS-'.fake()->unique()->numerify('####'),
        'nama_mk' => 'Pengujian GPS',
        'sks' => 2,
        'semester' => 1,
    ]);
    $kelas = Kelas::create([
        'dosen_id' => $dosen->id,
        'mata_kuliah_id' => $mataKuliah->id,
        'nama_kelas' => 'GPS',
        'kapasitas' => 30,
    ]);

    return JadwalKuliah::create([
        'kelas_id' => $kelas->id,
        'hari' => 'Senin',
        'jam_mulai' => '08:00',
        'jam_selesai' => '10:00',
        'ruangan' => 'A1',
    ]);
}

function buatPengaturanGps(): PengaturanGpsKehadiran
{
    return PengaturanGpsKehadiran::create([
        'latitude' => -6.2000000,
        'longitude' => 106.8166667,
        'radius_meter' => 100,
    ]);
}

it('records lecturer check-in and checkout coordinates within the configured radius', function () {
    $this->withoutVite();
    $dosen = Dosen::factory()->create();
    $jadwal = buatJadwalDosen($dosen);
    buatPengaturanGps();

    $this->actingAs($dosen->user)
        ->post(route('dosen.kehadiran.store'), [
            'jadwal_kuliah_id' => $jadwal->id,
            'status' => 'hadir',
            'latitude' => -6.2001,
            'longitude' => 106.8167,
        ])
        ->assertSessionHasNoErrors();

    $kehadiran = KehadiranDosen::firstOrFail();
    expect((float) $kehadiran->latitude_masuk)->toBe(-6.2001)
        ->and((float) $kehadiran->longitude_masuk)->toBe(106.8167);
    $catatanHonorarium = KehadiranMengajar::where('kehadiran_dosen_id', $kehadiran->id)->firstOrFail();
    expect($catatanHonorarium->dosen_id)->toBe($dosen->id)
        ->and($catatanHonorarium->kelas_id)->toBe($jadwal->kelas_id)
        ->and($catatanHonorarium->mata_kuliah_id)->toBe($jadwal->kelas->mata_kuliah_id)
        ->and($catatanHonorarium->status_hadir)->toBe('hadir')
        ->and($catatanHonorarium->status_verifikasi)->toBe(KehadiranMengajar::STATUS_PENDING);

    $this->actingAs($dosen->user)
        ->post(route('dosen.kehadiran.store'), [
            'jadwal_kuliah_id' => $jadwal->id,
            'status' => 'hadir',
            'latitude' => -6.2001,
            'longitude' => 106.8167,
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('kehadiran_mengajar', 1);

    $this->actingAs($dosen->user)
        ->post(route('dosen.kehadiran.checkout', $kehadiran), [
            'latitude' => -6.2001,
            'longitude' => 106.8167,
        ])
        ->assertSessionHasNoErrors();

    $kehadiran->refresh();
    expect((float) $kehadiran->latitude_keluar)->toBe(-6.2001)
        ->and((float) $kehadiran->longitude_keluar)->toBe(106.8167)
        ->and($kehadiran->jam_keluar)->not->toBeNull();

    expect($catatanHonorarium->fresh()->jam_selesai)->toBe($kehadiran->jam_keluar);
});

it('rejects lecturer check-in outside the configured campus radius', function () {
    $dosen = Dosen::factory()->create();
    $jadwal = buatJadwalDosen($dosen);
    buatPengaturanGps();

    $this->actingAs($dosen->user)
        ->post(route('dosen.kehadiran.store'), [
            'jadwal_kuliah_id' => $jadwal->id,
            'status' => 'hadir',
            'latitude' => -6.21,
            'longitude' => 106.8166667,
        ])
        ->assertSessionHasErrors('gps');

    $this->assertDatabaseCount('kehadiran_dosen', 0);
});

it('rejects lecturer check-in until an administrator configures a campus location', function () {
    $dosen = Dosen::factory()->create();
    $jadwal = buatJadwalDosen($dosen);

    $this->actingAs($dosen->user)
        ->post(route('dosen.kehadiran.store'), [
            'jadwal_kuliah_id' => $jadwal->id,
            'status' => 'hadir',
            'latitude' => -6.2,
            'longitude' => 106.8166667,
        ])
        ->assertSessionHasErrors('gps');

    $this->assertDatabaseCount('kehadiran_dosen', 0);
});

it('does not create a payable teaching attendance for non-present statuses', function () {
    $dosen = Dosen::factory()->create();
    $jadwal = buatJadwalDosen($dosen);

    $this->actingAs($dosen->user)
        ->post(route('dosen.kehadiran.store'), [
            'jadwal_kuliah_id' => $jadwal->id,
            'status' => 'izin',
            'keterangan' => 'Izin berhalangan',
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('kehadiran_dosen', 1);
    $this->assertDatabaseCount('kehadiran_mengajar', 0);
});

it('requires an administrator to configure the campus GPS location', function () {
    $superadmin = User::factory()->create(['role' => 'superadmin']);

    $this->actingAs($superadmin)
        ->get(route('admin.kehadiran-gps.edit'))
        ->assertOk()
        ->assertSee('Lokasi kampus untuk presensi')
        ->assertSee('campusMap')
        ->assertSee('radiusPreview');

    $this->actingAs($superadmin)
        ->put(route('admin.kehadiran-gps.update'), [
            'latitude' => -6.2,
            'longitude' => 106.8166667,
            'radius_meter' => 250,
        ])
        ->assertRedirect(route('admin.kehadiran-gps.edit'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('pengaturan_gps_kehadiran', [
        'latitude' => -6.2,
        'longitude' => 106.8166667,
        'radius_meter' => 250,
    ]);
});

it('does not allow lecturers to configure the campus GPS location', function () {
    $dosen = Dosen::factory()->create();

    $this->actingAs($dosen->user)
        ->get(route('admin.kehadiran-gps.edit'))
        ->assertForbidden();
});
