<?php

use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\Kelas;
use App\Models\MataKuliah;
use App\Models\Prodi;
use App\Models\Tugas;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows faculty admins to access LMS classes and manage tasks only in their faculty', function () {
    $this->withoutVite();

    $fakultas = Fakultas::factory()->create();
    $admin = User::factory()->create([
        'role' => 'admin_fakultas',
        'fakultas_id' => $fakultas->id,
    ]);

    $dosen = Dosen::factory()->create([
        'prodi_id' => Prodi::factory()->create(['fakultas_id' => $fakultas->id])->id,
    ]);
    $kelas = Kelas::create([
        'dosen_id' => $dosen->id,
        'mata_kuliah_id' => MataKuliah::create([
            'kode_mk' => 'LMS-'.fake()->unique()->numerify('####'),
            'nama_mk' => 'Kelas LMS Fakultas',
            'sks' => 3,
            'semester' => 1,
        ])->id,
        'nama_kelas' => 'A',
        'kapasitas' => 30,
    ]);

    $dosenLuar = Dosen::factory()->create();
    $kelasLuar = Kelas::create([
        'dosen_id' => $dosenLuar->id,
        'mata_kuliah_id' => MataKuliah::create([
            'kode_mk' => 'LMS-'.fake()->unique()->numerify('####'),
            'nama_mk' => 'Kelas LMS Fakultas Lain',
            'sks' => 2,
            'semester' => 1,
        ])->id,
        'nama_kelas' => 'B',
        'kapasitas' => 30,
    ]);
    $tugasLuar = Tugas::create([
        'kelas_id' => $kelasLuar->id,
        'judul' => 'Tugas luar fakultas',
        'deadline' => now()->addDay(),
    ]);

    $this->actingAs($admin)
        ->get(route('dosen.lms.index'))
        ->assertOk()
        ->assertSee('Kelas LMS Fakultas')
        ->assertDontSee('Kelas LMS Fakultas Lain');

    $this->actingAs($admin)
        ->get(route('dosen.materi.index', $kelas))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('dosen.tugas.index', $kelas))
        ->assertOk();

    $this->actingAs($admin)
        ->post(route('dosen.tugas.store', $kelas), [
            'judul' => 'Tugas admin fakultas',
            'deskripsi' => 'Tugas dalam lingkup fakultas.',
            'deadline' => now()->addDays(2)->toDateTimeString(),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tugas', [
        'kelas_id' => $kelas->id,
        'judul' => 'Tugas admin fakultas',
    ]);

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($admin)
        ->get(route('dosen.tugas.index', $kelasLuar)))
        ->toThrow(ModelNotFoundException::class);

    expect(fn () => $this->actingAs($admin)
        ->get(route('dosen.tugas.show', [$kelas, $tugasLuar])))
        ->toThrow(ModelNotFoundException::class);
});

it('preserves lecturer access to their own LMS classes', function () {
    $this->withoutVite();

    $dosen = Dosen::factory()->create();
    $kelas = Kelas::create([
        'dosen_id' => $dosen->id,
        'mata_kuliah_id' => MataKuliah::create([
            'kode_mk' => 'LMS-'.fake()->unique()->numerify('####'),
            'nama_mk' => 'Kelas LMS Dosen',
            'sks' => 3,
            'semester' => 1,
        ])->id,
        'nama_kelas' => 'A',
        'kapasitas' => 30,
    ]);

    $this->actingAs($dosen->user)
        ->get(route('dosen.lms.index'))
        ->assertOk()
        ->assertSee('Kelas LMS Dosen');

    $this->actingAs($dosen->user)
        ->get(route('dosen.materi.index', $kelas))
        ->assertOk();
});
