<?php

use App\Models\Dosen;
use App\Models\Honorarium;
use App\Models\KehadiranMengajar;
use App\Models\Kelas;
use App\Models\MataKuliah;
use App\Models\TarifHonorarium;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('calculates draft honorarium from approved teaching attendance and manual incentives', function () {
    $this->withoutVite();
    $admin = User::factory()->create(['role' => 'superadmin']);
    $dosen = Dosen::factory()->create();
    $mataKuliah = MataKuliah::create([
        'kode_mk' => 'MK-HNR-01',
        'nama_mk' => 'Pengantar Pengujian',
        'sks' => 4,
        'semester' => 1,
    ]);
    $kelas = Kelas::create([
        'dosen_id' => $dosen->id,
        'mata_kuliah_id' => $mataKuliah->id,
        'nama_kelas' => 'A',
        'kapasitas' => 30,
    ]);
    TarifHonorarium::create([
        'basis_tarif' => 'per_sks',
        'nominal' => 10000,
        'berlaku_mulai' => '2026-09-01',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.honorarium.kehadiran.store'), [
            'dosen_id' => $dosen->id,
            'mata_kuliah_id' => $mataKuliah->id,
            'kelas_id' => $kelas->id,
            'tanggal' => '2026-09-10',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
            'status_hadir' => 'hadir',
        ])
        ->assertSessionHasNoErrors();

    $kehadiran = KehadiranMengajar::firstOrFail();
    expect($kehadiran->status_verifikasi)->toBe(KehadiranMengajar::STATUS_PENDING);

    $this->actingAs($admin)
        ->patch(route('admin.honorarium.kehadiran.verify', $kehadiran), [
            'status_verifikasi' => KehadiranMengajar::STATUS_APPROVED,
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->post(route('admin.honorarium.insentif.store'), [
            'dosen_id' => $dosen->id,
            'jenis_insentif' => 'Transportasi',
            'nominal' => 15000,
            'tanggal' => '2026-09-15',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->post(route('admin.honorarium.generate'), [
            'bulan' => 9,
            'tahun' => 2026,
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('honorarium', [
        'dosen_id' => $dosen->id,
        'bulan' => 9,
        'tahun' => 2026,
        'total_kehadiran' => 1,
        'total_honor_mengajar' => 40000,
        'total_insentif_manual' => 15000,
        'total_penerimaan' => 55000,
        'status_pembayaran' => Honorarium::STATUS_DRAFT,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.honorarium.index', ['bulan' => 9, 'tahun' => 2026]))
        ->assertOk()
        ->assertSee('Rekap');

    $this->actingAs($admin)
        ->get(route('admin.honorarium.export.pdf', ['bulan' => 9, 'tahun' => 2026]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($admin)
        ->get(route('admin.honorarium.export.excel', ['bulan' => 9, 'tahun' => 2026]))
        ->assertOk();
});

it('does not count unverified or non-present meetings and prevents editing paid periods', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $dosen = Dosen::factory()->create();
    $mataKuliah = MataKuliah::create([
        'kode_mk' => 'MK-HNR-02',
        'nama_mk' => 'Metode Pengujian',
        'sks' => 2,
        'semester' => 2,
    ]);
    $kelas = Kelas::create([
        'dosen_id' => $dosen->id,
        'mata_kuliah_id' => $mataKuliah->id,
        'nama_kelas' => 'B',
        'kapasitas' => 30,
    ]);
    TarifHonorarium::create([
        'basis_tarif' => 'per_sks',
        'nominal' => 5000,
        'berlaku_mulai' => '2026-09-01',
    ]);
    KehadiranMengajar::create([
        'dosen_id' => $dosen->id,
        'mata_kuliah_id' => $mataKuliah->id,
        'kelas_id' => $kelas->id,
        'tanggal' => '2026-09-12',
        'jam_mulai' => '08:00',
        'jam_selesai' => '10:00',
        'status_hadir' => 'hadir',
        'status_verifikasi' => KehadiranMengajar::STATUS_PENDING,
    ]);
    KehadiranMengajar::create([
        'dosen_id' => $dosen->id,
        'mata_kuliah_id' => $mataKuliah->id,
        'kelas_id' => $kelas->id,
        'tanggal' => '2026-09-13',
        'jam_mulai' => '08:00',
        'jam_selesai' => '10:00',
        'status_hadir' => 'izin',
        'status_verifikasi' => KehadiranMengajar::STATUS_APPROVED,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.honorarium.generate'), ['bulan' => 9, 'tahun' => 2026])
        ->assertSessionHasNoErrors();

    $summary = Honorarium::where('dosen_id', $dosen->id)->firstOrFail();
    expect($summary->total_kehadiran)->toBe(0)
        ->and((float) $summary->total_honor_mengajar)->toBe(0.0);

    $summary->update(['status_pembayaran' => Honorarium::STATUS_PAID]);

    $this->actingAs($admin)
        ->post(route('admin.honorarium.insentif.store'), [
            'dosen_id' => $dosen->id,
            'jenis_insentif' => 'Tambahan',
            'nominal' => 1000,
            'tanggal' => '2026-09-15',
        ])
        ->assertStatus(409);
});

it('limits lecturer honorarium history and slips to the authenticated lecturer', function () {
    $this->withoutVite();
    $dosen = Dosen::factory()->create();
    $lain = Dosen::factory()->create();
    Honorarium::create([
        'dosen_id' => $dosen->id,
        'bulan' => 8,
        'tahun' => 2026,
        'status_pembayaran' => Honorarium::STATUS_DRAFT,
    ]);
    $milikLain = Honorarium::create([
        'dosen_id' => $lain->id,
        'bulan' => 8,
        'tahun' => 2026,
        'status_pembayaran' => Honorarium::STATUS_DRAFT,
    ]);

    $this->actingAs($dosen->user)
        ->get(route('dosen.honorarium.index'))
        ->assertOk()
        ->assertSee($dosen->user->name)
        ->assertDontSee($lain->user->name);

    $this->actingAs($dosen->user)
        ->get(route('dosen.honorarium.slip', $dosen->honorarium()->first()))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($dosen->user)
        ->get(route('dosen.honorarium.slip', $milikLain)))
        ->toThrow(NotFoundHttpException::class);
});
