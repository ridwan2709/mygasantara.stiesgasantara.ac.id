<?php

use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\MataKuliah;
use App\Models\Prodi;
use App\Models\TahunAkademik;
use App\Models\User;
use App\Services\MasterDataImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $fakultas = Fakultas::create(['nama' => 'Fakultas Uji']);
    $this->prodi = Prodi::create([
        'fakultas_id' => $fakultas->id,
        'nama' => 'Program Studi Uji',
    ]);
    $this->admin = User::factory()->create(['role' => 'superadmin']);
    $this->tahunAkademik = TahunAkademik::create([
        'tahun' => '2026/2027',
        'semester' => 'Ganjil',
        'is_active' => true,
    ]);
    $this->dosen = Dosen::factory()->create(['prodi_id' => $this->prodi->id]);
    $this->mataKuliah = MataKuliah::create([
        'kode_mk' => 'MK-UJI',
        'nama_mk' => 'Mata Kuliah Uji',
        'sks' => 3,
        'semester' => 1,
        'prodi_id' => $this->prodi->id,
    ]);
    $this->mataKuliahUmum = MataKuliah::create([
        'kode_mk' => 'MK-UMUM',
        'nama_mk' => 'Mata Kuliah Umum',
        'sks' => 2,
        'semester' => 1,
        'prodi_id' => $this->prodi->id,
    ]);
    $this->kelasA = Kelas::create([
        'mata_kuliah_id' => $this->mataKuliah->id,
        'dosen_id' => $this->dosen->id,
        'tahun_akademik_id' => $this->tahunAkademik->id,
        'nama_kelas' => 'A',
        'kapasitas' => 2,
        'is_closed' => false,
    ]);
    $this->kelasB = Kelas::create([
        'mata_kuliah_id' => $this->mataKuliah->id,
        'dosen_id' => $this->dosen->id,
        'tahun_akademik_id' => $this->tahunAkademik->id,
        'nama_kelas' => 'B',
        'kapasitas' => 2,
        'is_closed' => false,
    ]);
    $this->kelasUmum = Kelas::create([
        'mata_kuliah_id' => $this->mataKuliahUmum->id,
        'dosen_id' => $this->dosen->id,
        'tahun_akademik_id' => $this->tahunAkademik->id,
        'nama_kelas' => 'Umum',
        'kapasitas' => 3,
        'is_closed' => false,
    ]);
    $this->mahasiswaA1 = Mahasiswa::factory()->create([
        'prodi_id' => $this->prodi->id,
        'angkatan' => '2026',
        'rombel' => 'A',
    ]);
    $this->mahasiswaA2 = Mahasiswa::factory()->create([
        'prodi_id' => $this->prodi->id,
        'angkatan' => '2026',
        'rombel' => 'A',
    ]);
    $this->mahasiswaB = Mahasiswa::factory()->create([
        'prodi_id' => $this->prodi->id,
        'angkatan' => '2026',
        'rombel' => 'B',
    ]);
});

it('allows administrators to assign a rombel when creating a student', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.mahasiswa.store'), [
            'name' => 'Mahasiswa Baru',
            'email' => 'mahasiswa-baru@example.test',
            'password' => 'password123',
            'nim' => '20260001',
            'prodi_id' => $this->prodi->id,
            'angkatan' => '2026',
            'rombel' => 'b',
        ])
        ->assertRedirect();

    expect(Mahasiswa::query()->where('nim', '20260001')->value('rombel'))->toBe('B');
});

it('imports student rombels while remaining compatible with older student templates', function () {
    $service = app(MasterDataImportService::class);
    $headers = 'nim,nama,email,password,prodi,angkatan,dosen_pa_nidn,status';
    $olderTemplate = UploadedFile::fake()->createWithContent(
        'mahasiswa-lama.csv',
        $headers."\n20260002,Mahasiswa Lama,mahasiswa-lama@example.test,password123,\"{$this->prodi->nama}\",2026,,aktif\n",
    );
    $assignedTemplate = UploadedFile::fake()->createWithContent(
        'mahasiswa-rombel.csv',
        $headers.",rombel\n20260003,Mahasiswa Rombel,mahasiswa-rombel@example.test,password123,\"{$this->prodi->nama}\",2026,,aktif,b\n",
    );

    expect($service->import('mahasiswa', $olderTemplate, $this->admin))->toBe(1)
        ->and($service->import('mahasiswa', $assignedTemplate, $this->admin))->toBe(1)
        ->and(Mahasiswa::query()->where('nim', '20260002')->value('rombel'))->toBeNull()
        ->and(Mahasiswa::query()->where('nim', '20260003')->value('rombel'))->toBe('B');
});

it('shows matching rombel classes and shared classes in the bulk KRS form', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.krs.create', [
            'prodi_id' => $this->prodi->id,
            'angkatan' => '2026',
            'rombel' => 'A',
            'semester' => 1,
        ]))
        ->assertOk()
        ->assertSee('Rombel A')
        ->assertSee('Kelas A')
        ->assertSee('Kelas Umum')
        ->assertDontSee('Kelas B');
});

it('creates matching and shared classes for students in the selected rombel', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.krs.store'), [
            'prodi_id' => $this->prodi->id,
            'angkatan' => '2026',
            'rombel' => 'A',
            'semester' => 1,
            'mata_kuliah_ids' => [$this->mataKuliah->id, $this->mataKuliahUmum->id],
            'kelas_per_mata_kuliah' => [
                $this->mataKuliah->id => $this->kelasA->id,
                $this->mataKuliahUmum->id => $this->kelasUmum->id,
            ],
        ])
        ->assertRedirect(route('admin.krs-approval.index', [
            'status' => 'approved',
            'prodi_id' => $this->prodi->id,
            'angkatan' => '2026',
            'rombel' => 'A',
        ]));

    expect(Krs::query()->where('mahasiswa_id', $this->mahasiswaA1->id)->firstOrFail()->krsDetail()->value('kelas_id'))
        ->toBe($this->kelasA->id)
        ->and(Krs::query()->where('mahasiswa_id', $this->mahasiswaA1->id)->firstOrFail()->krsDetail()->where('kelas_id', $this->kelasUmum->id)->exists())->toBeTrue()
        ->and(Krs::query()->where('mahasiswa_id', $this->mahasiswaA2->id)->firstOrFail()->krsDetail()->where('kelas_id', $this->kelasUmum->id)->exists())->toBeTrue()
        ->and(Krs::query()->where('mahasiswa_id', $this->mahasiswaB->id)->exists())->toBeFalse();

    $this->actingAs($this->admin)
        ->get(route('admin.mahasiswa.index'))
        ->assertOk()
        ->assertSee('Rombel');

    $this->actingAs($this->mahasiswaA1->user)
        ->get(route('mahasiswa.krs.index'))
        ->assertOk()
        ->assertSee('Kelas A');
});

it('keeps the approved KRS list scoped to the selected rombel', function () {
    $existingKrsForRombelB = Krs::create([
        'mahasiswa_id' => $this->mahasiswaB->id,
        'tahun_akademik_id' => $this->tahunAkademik->id,
        'status' => 'approved',
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.krs.store'), [
            'prodi_id' => $this->prodi->id,
            'angkatan' => '2026',
            'rombel' => 'A',
            'semester' => 1,
            'mata_kuliah_ids' => [$this->mataKuliah->id],
            'kelas_per_mata_kuliah' => [$this->mataKuliah->id => $this->kelasA->id],
        ])
        ->assertRedirect(route('admin.krs-approval.index', [
            'status' => 'approved',
            'prodi_id' => $this->prodi->id,
            'angkatan' => '2026',
            'rombel' => 'A',
        ]));

    $this->actingAs($this->admin)
        ->get(route('admin.krs-approval.index', [
            'status' => 'approved',
            'prodi_id' => $this->prodi->id,
            'angkatan' => '2026',
            'rombel' => 'A',
        ]))
        ->assertOk()
        ->assertSee($this->mahasiswaA1->nim)
        ->assertDontSee($this->mahasiswaB->nim);

    expect($existingKrsForRombelB->fresh()->status)->toBe('approved');

    $this->actingAs($this->admin)
        ->get(route('admin.krs-approval.index', ['status' => 'approved']))
        ->assertOk()
        ->assertSee($this->mahasiswaB->nim)
        ->assertSee('Rombel');
});

it('rejects a class that does not match the selected rombel', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.krs.store'), [
            'prodi_id' => $this->prodi->id,
            'angkatan' => '2026',
            'rombel' => 'A',
            'semester' => 1,
            'mata_kuliah_ids' => [$this->mataKuliah->id],
            'kelas_per_mata_kuliah' => [$this->mataKuliah->id => $this->kelasB->id],
        ])
        ->assertSessionHasErrors('kelas_per_mata_kuliah');

    expect(Krs::query()->exists())->toBeFalse();
});
