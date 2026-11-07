<?php

use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\JadwalKuliah;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\KrsDetail;
use App\Models\Mahasiswa;
use App\Models\MataKuliah;
use App\Models\Pertemuan;
use App\Models\Prodi;
use App\Models\TahunAkademik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows faculty admins to manage attendance for classes in their faculty only', function () {
    $this->withoutVite();

    $fakultas = Fakultas::factory()->create();
    $admin = User::factory()->create([
        'role' => 'admin_fakultas',
        'fakultas_id' => $fakultas->id,
    ]);
    $dosen = Dosen::factory()->create([
        'prodi_id' => Prodi::factory()->create(['fakultas_id' => $fakultas->id])->id,
    ]);
    $mataKuliah = MataKuliah::create([
        'kode_mk' => 'PRES-'.fake()->unique()->numerify('####'),
        'nama_mk' => 'Presensi Fakultas',
        'sks' => 3,
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

    $dosenLuarFakultas = Dosen::factory()->create();
    $kelasLuarFakultas = Kelas::create([
        'dosen_id' => $dosenLuarFakultas->id,
        'mata_kuliah_id' => MataKuliah::create([
            'kode_mk' => 'OTHER-'.fake()->unique()->numerify('####'),
            'nama_mk' => 'Presensi Fakultas Lain',
            'sks' => 2,
            'semester' => 1,
        ])->id,
        'nama_kelas' => 'B',
        'kapasitas' => 30,
    ]);
    $pertemuanLuarFakultas = Pertemuan::create([
        'jadwal_kuliah_id' => JadwalKuliah::create([
            'kelas_id' => $kelasLuarFakultas->id,
            'hari' => 'Selasa',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
            'ruangan' => 'B1',
        ])->id,
        'pertemuan_ke' => 1,
        'tanggal' => '2026-11-07',
        'status' => Pertemuan::STATUS_TERLAKSANA,
    ]);

    $this->actingAs($admin)
        ->get(route('dosen.presensi.index'))
        ->assertOk()
        ->assertSee('Presensi Fakultas')
        ->assertDontSee('Presensi Fakultas Lain');

    $this->actingAs($admin)
        ->get(route('dosen.presensi.pertemuan.create', $kelas))
        ->assertOk();

    $response = $this->actingAs($admin)
        ->post(route('dosen.presensi.pertemuan.store', $kelas), [
            'jadwal_kuliah_id' => $jadwal->id,
            'pertemuan_ke' => 1,
            'tanggal' => '2026-11-07',
            'materi' => 'Materi presensi',
        ]);

    $pertemuan = Pertemuan::where('jadwal_kuliah_id', $jadwal->id)->firstOrFail();
    $response->assertRedirect(route('dosen.presensi.input', $pertemuan));

    $this->actingAs($admin)
        ->get(route('dosen.presensi.input', $pertemuan))
        ->assertOk()
        ->assertSee($mahasiswa->user->name);

    $this->actingAs($admin)
        ->post(route('dosen.presensi.store', $pertemuan), [
            'presensi' => [$mahasiswa->id => 'hadir'],
        ])
        ->assertRedirect(route('dosen.presensi.kelas', $kelas));

    $this->assertDatabaseHas('presensi', [
        'pertemuan_id' => $pertemuan->id,
        'mahasiswa_id' => $mahasiswa->id,
        'status' => 'hadir',
    ]);

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($admin)
        ->get(route('dosen.presensi.kelas', $kelasLuarFakultas)))
        ->toThrow(HttpException::class);

    expect(fn () => $this->actingAs($admin)
        ->post(route('dosen.presensi.store', $pertemuan), [
            'presensi' => [Mahasiswa::factory()->create()->id => 'hadir'],
        ]))
        ->toThrow(HttpException::class);
});

it('preserves lecturer access to their own attendance classes', function () {
    $this->withoutVite();

    $dosen = Dosen::factory()->create();
    $kelas = Kelas::create([
        'dosen_id' => $dosen->id,
        'mata_kuliah_id' => MataKuliah::create([
            'kode_mk' => 'DOSEN-'.fake()->unique()->numerify('####'),
            'nama_mk' => 'Kelas Dosen',
            'sks' => 2,
            'semester' => 1,
        ])->id,
        'nama_kelas' => 'A',
        'kapasitas' => 30,
    ]);

    $this->actingAs($dosen->user)
        ->get(route('dosen.presensi.index'))
        ->assertOk()
        ->assertSee('Kelas Dosen');

    $this->actingAs($dosen->user)
        ->get(route('dosen.presensi.kelas', $kelas))
        ->assertOk();
});
