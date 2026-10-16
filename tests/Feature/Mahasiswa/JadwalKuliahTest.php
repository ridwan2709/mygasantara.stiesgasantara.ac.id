<?php

use App\Models\Dosen;
use App\Models\JadwalKuliah;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\KrsDetail;
use App\Models\Mahasiswa;
use App\Models\MataKuliah;
use App\Models\TahunAkademik;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

it('opens the first scheduled day when the student has no classes today', function () {
    $this->withoutVite();
    Carbon::setTestNow('2026-10-19 09:00:00');

    $mahasiswa = Mahasiswa::factory()->create();
    $tahunAkademik = TahunAkademik::factory()->create(['is_active' => true]);
    $dosen = Dosen::factory()->create();
    $mataKuliah = MataKuliah::create([
        'kode_mk' => 'MK-JADWAL-01',
        'nama_mk' => 'Pengantar Pengujian',
        'sks' => 3,
        'semester' => 1,
    ]);
    $kelas = Kelas::create([
        'dosen_id' => $dosen->id,
        'mata_kuliah_id' => $mataKuliah->id,
        'nama_kelas' => 'A',
        'kapasitas' => 30,
    ]);
    $kelas->tahunAkademik()->associate($tahunAkademik);
    $kelas->save();

    $krs = Krs::create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => $tahunAkademik->id,
        'status' => 'approved',
    ]);
    KrsDetail::create([
        'krs_id' => $krs->id,
        'kelas_id' => $kelas->id,
    ]);
    JadwalKuliah::create([
        'kelas_id' => $kelas->id,
        'hari' => 'Rabu',
        'jam_mulai' => '10:00',
        'jam_selesai' => '12:00',
        'ruangan' => 'Ruang 1',
    ]);

    $this->actingAs($mahasiswa->user)
        ->get(route('mahasiswa.jadwal.index'))
        ->assertOk()
        ->assertSeeHtml("selectedDay: 'Rabu'")
        ->assertSee('Pengantar Pengujian')
        ->assertSee('Ruang 1');
});
