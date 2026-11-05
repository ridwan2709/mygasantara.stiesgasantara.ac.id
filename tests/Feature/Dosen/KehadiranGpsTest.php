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

afterEach(function () {
    \Carbon\Carbon::setTestNow();
});

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

it('renders the dashboard attendance button with its class schedule', function () {
    $this->withoutVite();
    $dosen = Dosen::factory()->create();
    $jadwal = buatJadwalDosen($dosen);
    $hariMapping = [
        'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu',
    ];
    $jadwal->update(['hari' => $hariMapping[now()->format('l')]]);

    $this->actingAs($dosen->user)
        ->get(route('dosen.dashboard'))
        ->assertOk()
        ->assertSee('showModal('.$jadwal->id)
        ->assertSee('Pengujian GPS');
});

it('shows the class currently in progress instead of the first scheduled class', function () {
    $this->withoutVite();
    $this->travelTo(\Carbon\Carbon::parse('2026-10-20 10:30:00'));

    $dosen = Dosen::factory()->create();
    $pastSchedule = buatJadwalDosen($dosen);
    $ongoingSchedule = buatJadwalDosen($dosen);
    $upcomingSchedule = buatJadwalDosen($dosen);
    $today = now()->format('l');
    $dayMapping = [
        'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu',
    ];

    $pastSchedule->update(['hari' => $dayMapping[$today], 'jam_mulai' => '08:00', 'jam_selesai' => '09:00']);
    $ongoingSchedule->update(['hari' => $dayMapping[$today], 'jam_mulai' => '10:00', 'jam_selesai' => '11:00']);
    $upcomingSchedule->update(['hari' => $dayMapping[$today], 'jam_mulai' => '12:00', 'jam_selesai' => '13:00']);

    $this->actingAs($dosen->user)
        ->get(route('dosen.dashboard'))
        ->assertOk()
        ->assertSee('showModal('.$ongoingSchedule->id)
        ->assertDontSee('showModal('.$pastSchedule->id)
        ->assertDontSee('showModal('.$upcomingSchedule->id);
});

it('shows the next class when no class is currently in progress', function () {
    $this->withoutVite();
    $this->travelTo(\Carbon\Carbon::parse('2026-10-20 10:30:00'));

    $dosen = Dosen::factory()->create();
    $pastSchedule = buatJadwalDosen($dosen);
    $upcomingSchedule = buatJadwalDosen($dosen);
    $today = now()->format('l');
    $dayMapping = [
        'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu',
    ];

    $pastSchedule->update(['hari' => $dayMapping[$today], 'jam_mulai' => '08:00', 'jam_selesai' => '09:00']);
    $upcomingSchedule->update(['hari' => $dayMapping[$today], 'jam_mulai' => '12:00', 'jam_selesai' => '13:00']);

    $this->actingAs($dosen->user)
        ->get(route('dosen.dashboard'))
        ->assertOk()
        ->assertSee('showModal('.$upcomingSchedule->id)
        ->assertDontSee('showModal('.$pastSchedule->id);
});

it('renders the lecturer attendance page with attendance data', function () {
    $this->withoutVite();
    $dosen = Dosen::factory()->create();

    $this->actingAs($dosen->user)
        ->get(route('dosen.kehadiran.index'))
        ->assertOk()
        ->assertSee('Kehadiran Mengajar');
});

it('allows staff to open the add attendance route', function () {
    $this->withoutVite();
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)
        ->get(route('dosen.kehadiran.create'))
        ->assertOk()
        ->assertSee('Absensi harian')
        ->assertSee(route('dosen.kehadiran.store'));
});

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
