<?php

use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\KrsDetail;
use App\Models\Mahasiswa;
use App\Models\MataKuliah;
use App\Models\Prodi;
use App\Models\TahunAkademik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows faculty admins to view and enter grades for classes within their faculty only', function () {
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
        'kode_mk' => 'TEST-'.fake()->unique()->numerify('####'),
        'nama_mk' => 'Mata Kuliah Fakultas',
        'sks' => 3,
        'semester' => 1,
    ]);
    $kelas = Kelas::create([
        'dosen_id' => $dosen->id,
        'mata_kuliah_id' => $mataKuliah->id,
        'nama_kelas' => 'A',
        'kapasitas' => 30,
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
            'nama_mk' => 'Mata Kuliah Fakultas Lain',
            'sks' => 2,
            'semester' => 1,
        ])->id,
        'nama_kelas' => 'B',
        'kapasitas' => 30,
    ]);

    $this->actingAs($admin)
        ->get(route('dosen.penilaian.index'))
        ->assertOk()
        ->assertSee('Mata Kuliah Fakultas')
        ->assertDontSee('Mata Kuliah Fakultas Lain');

    $this->actingAs($admin)
        ->get(route('dosen.penilaian.show', $kelas->id))
        ->assertOk()
        ->assertSee($mahasiswa->user->name);

    $this->actingAs($admin)
        ->post(route('dosen.penilaian.store', $kelas->id), [
            'nilai' => [$mahasiswa->id => 90],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('nilai', [
        'kelas_id' => $kelas->id,
        'mahasiswa_id' => $mahasiswa->id,
        'nilai_angka' => 90,
    ]);

    $this->actingAs($admin)
        ->get(route('dosen.penilaian.show', $kelasLuarFakultas->id))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('dosen.penilaian.store', $kelas->id), [
            'nilai' => [Mahasiswa::factory()->create()->id => 90],
        ])
        ->assertForbidden();
});
