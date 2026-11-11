<?php

use App\Models\Dosen;
use App\Models\JadwalKuliah;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\KrsDetail;
use App\Models\Mahasiswa;
use App\Models\MataKuliah;
use App\Models\PengaturanGpsKehadiran;
use App\Models\Pertemuan;
use App\Models\Presensi;
use App\Models\TahunAkademik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatDataPresensiMandiri(): array
{
    $dosen = Dosen::factory()->create();
    $mataKuliah = MataKuliah::create([
        'kode_mk' => 'SELF-'.fake()->unique()->numerify('####'),
        'nama_mk' => 'Presensi Mandiri',
        'sks' => 2,
        'semester' => 1,
    ]);
    $kelas = Kelas::create([
        'dosen_id' => $dosen->id,
        'mata_kuliah_id' => $mataKuliah->id,
        'nama_kelas' => 'A',
        'kapasitas' => 30,
    ]);
    $jadwal = JadwalKuliah::create([
        'kelas_id' => $kelas->id,
        'hari' => 'Senin',
        'jam_mulai' => '08:00',
        'jam_selesai' => '10:00',
        'ruangan' => 'A1',
    ]);
    $mahasiswa = Mahasiswa::factory()->create();
    $krs = Krs::create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => TahunAkademik::factory()->create()->id,
        'status' => 'approved',
    ]);
    KrsDetail::create([
        'krs_id' => $krs->id,
        'kelas_id' => $kelas->id,
    ]);

    return compact('kelas', 'jadwal', 'mahasiswa');
}

function buatPertemuanPresensiMandiri(JadwalKuliah $jadwal, int $pertemuanKe, string $tanggal): Pertemuan
{
    return Pertemuan::create([
        'jadwal_kuliah_id' => $jadwal->id,
        'pertemuan_ke' => $pertemuanKe,
        'tanggal' => $tanggal,
        'materi' => 'Materi '.$pertemuanKe,
        'status' => Pertemuan::STATUS_TERLAKSANA,
    ]);
}

function buatPengaturanGpsPresensiMandiri(): PengaturanGpsKehadiran
{
    return PengaturanGpsKehadiran::create([
        'latitude' => -6.2,
        'longitude' => 106.8166667,
        'radius_meter' => 100,
    ]);
}

it('allows an enrolled student to check in to a meeting with gps from attendance history', function () {
    $this->withoutVite();
    $data = buatDataPresensiMandiri();
    buatPengaturanGpsPresensiMandiri();
    $pertemuanLama = buatPertemuanPresensiMandiri($data['jadwal'], 1, '2026-11-09');
    $pertemuanTerbaru = buatPertemuanPresensiMandiri($data['jadwal'], 2, '2026-11-10');

    $response = $this->actingAs($data['mahasiswa']->user)
        ->get(route('mahasiswa.presensi.show', $data['kelas']))
        ->assertOk()
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)')
        ->assertSee('Presensi Sekarang');

    expect(strpos($response->getContent(), 'Pertemuan 2'))
        ->toBeLessThan(strpos($response->getContent(), 'Pertemuan 1'));

    $this->actingAs($data['mahasiswa']->user)
        ->post(route('mahasiswa.presensi.store', $pertemuanTerbaru), [
            'latitude' => -6.2001,
            'longitude' => 106.8167,
        ])
        ->assertRedirect(route('mahasiswa.presensi.show', $data['kelas']))
        ->assertSessionHas('success', 'Presensi berhasil dicatat.');

    $presensi = Presensi::query()
        ->where('pertemuan_id', $pertemuanTerbaru->id)
        ->where('mahasiswa_id', $data['mahasiswa']->id)
        ->firstOrFail();

    expect($presensi->status)->toBe(Presensi::STATUS_HADIR)
        ->and($presensi->waktu_presensi)->not->toBeNull()
        ->and($presensi->latitude)->toBe(-6.2001)
        ->and($presensi->longitude)->toBe(106.8167);

    $this->assertDatabaseMissing('presensi', [
        'pertemuan_id' => $pertemuanLama->id,
        'mahasiswa_id' => $data['mahasiswa']->id,
    ]);
});

it('rejects student self attendance outside the configured campus radius', function () {
    $data = buatDataPresensiMandiri();
    buatPengaturanGpsPresensiMandiri();
    $pertemuan = buatPertemuanPresensiMandiri($data['jadwal'], 1, '2026-11-10');

    $this->actingAs($data['mahasiswa']->user)
        ->from(route('mahasiswa.presensi.show', $data['kelas']))
        ->post(route('mahasiswa.presensi.store', $pertemuan), [
            'latitude' => -6.21,
            'longitude' => 106.8166667,
        ])
        ->assertRedirect(route('mahasiswa.presensi.show', $data['kelas']))
        ->assertSessionHasErrors('gps');

    $this->assertDatabaseCount('presensi', 0);
});

it('does not allow students to self record attendance for another class or overwrite another status', function () {
    $data = buatDataPresensiMandiri();
    buatPengaturanGpsPresensiMandiri();
    $pertemuan = buatPertemuanPresensiMandiri($data['jadwal'], 1, '2026-11-10');
    $mahasiswaLain = Mahasiswa::factory()->create();
    $userLain = $mahasiswaLain->user;

    $this->actingAs($userLain)
        ->post(route('mahasiswa.presensi.store', $pertemuan), [
            'latitude' => -6.2,
            'longitude' => 106.8166667,
        ])
        ->assertForbidden();

    $presensiIzin = Presensi::create([
        'pertemuan_id' => $pertemuan->id,
        'mahasiswa_id' => $data['mahasiswa']->id,
        'status' => Presensi::STATUS_IZIN,
    ]);

    $this->actingAs($data['mahasiswa']->user)
        ->from(route('mahasiswa.presensi.show', $data['kelas']))
        ->post(route('mahasiswa.presensi.store', $pertemuan), [
            'latitude' => -6.2,
            'longitude' => 106.8166667,
        ])
        ->assertSessionHasErrors('presensi');

    expect($presensiIzin->fresh()->status)->toBe(Presensi::STATUS_IZIN);
});

it('lets a student with an alpa entry check in and updates it with gps coordinates', function () {
    $data = buatDataPresensiMandiri();
    buatPengaturanGpsPresensiMandiri();
    $pertemuan = buatPertemuanPresensiMandiri($data['jadwal'], 1, '2026-11-10');
    $presensi = Presensi::create([
        'pertemuan_id' => $pertemuan->id,
        'mahasiswa_id' => $data['mahasiswa']->id,
        'status' => Presensi::STATUS_ALPA,
    ]);

    $this->actingAs($data['mahasiswa']->user)
        ->post(route('mahasiswa.presensi.store', $pertemuan), [
            'latitude' => -6.2,
            'longitude' => 106.8166667,
        ])
        ->assertSessionHas('success');

    expect($presensi->fresh()->status)->toBe(Presensi::STATUS_HADIR)
        ->and($presensi->fresh()->latitude)->toBe(-6.2);
});
