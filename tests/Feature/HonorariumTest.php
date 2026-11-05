<?php

use App\Models\Dosen;
use App\Models\Honorarium;
use App\Models\KehadiranDosen;
use App\Models\KehadiranMengajar;
use App\Models\Kelas;
use App\Models\MataKuliah;
use App\Models\TarifHonorPegawai;
use App\Models\TarifHonorarium;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('stores employee honorarium in the existing honorarium table', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('honorarium_pegawai'))->toBeFalse()
        ->and(\Illuminate\Support\Facades\Schema::hasColumn('honorarium', 'user_id'))->toBeTrue();
});

it('includes employee honorarium in the monthly pdf export', function () {
    $this->withoutVite();
    $admin = User::factory()->create(['role' => 'superadmin']);
    $pegawai = User::factory()->create([
        'name' => 'Pegawai Untuk PDF',
        'role' => 'staff',
    ]);
    KehadiranDosen::query()->create([
        'user_id' => $pegawai->id,
        'tanggal' => today()->toDateString(),
        'jam_masuk' => '08:00:00',
        'jam_keluar' => '17:00:00',
        'status' => KehadiranDosen::STATUS_HADIR,
    ]);
    TarifHonorPegawai::query()->create([
        'user_id' => $pegawai->id,
        'nominal' => 85000,
        'berlaku_mulai' => today()->startOfMonth()->toDateString(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.honorarium.export.pdf', ['bulan' => today()->month, 'tahun' => today()->year]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $html = view('admin.keuangan.pdf', [
        'bulan' => today()->month,
        'tahun' => today()->year,
        'honorariumList' => collect(),
        'honorariumPegawaiList' => collect([[
            'name' => 'Pegawai Untuk PDF',
            'total_kehadiran' => 1,
            'total_honor' => '85000.00',
            'tarif_belum_ada' => 0,
            'status_pembayaran' => 'draft',
            'tanggal_bayar' => null,
        ]]),
    ])->render();

    expect($html)->toContain('Pegawai Untuk PDF')
        ->and($html)->toContain('85.000,00');
});

it('shows completed employee attendance and calculates honor from the applicable employee rate', function () {
    $this->withoutVite();
    $admin = User::factory()->create(['role' => 'superadmin']);
    $pegawai = User::factory()->create([
        'name' => 'Pegawai Penguji',
        'role' => 'staff',
        'boleh_absen' => false,
    ]);
    $kehadiranData = [
        'tanggal' => today()->toDateString(),
        'status' => KehadiranDosen::STATUS_HADIR,
        'jam_masuk' => '08:00:00',
    ];
    $kehadiran = KehadiranDosen::query()->create([
        ...$kehadiranData,
        'user_id' => $pegawai->id,
    ]);
    KehadiranDosen::query()->create([
        ...$kehadiranData,
        'user_id' => User::factory()->create(['role' => 'staff'])->id,
    ]);
    TarifHonorPegawai::query()->create([
        'user_id' => $pegawai->id,
        'nominal' => 100000,
        'berlaku_mulai' => today()->startOfMonth()->toDateString(),
    ]);

    $this->actingAs($pegawai)
        ->post(route('pegawai.kehadiran.keluar'))
        ->assertRedirect(route('pegawai.kehadiran.index'))
        ->assertSessionHas('success');

    $this->actingAs($admin)
        ->get(route('admin.honorarium.index', ['bulan' => today()->month, 'tahun' => today()->year]))
        ->assertOk()
        ->assertSee('Rekap Honor Pegawai')
        ->assertSee('Pegawai Penguji')
        ->assertSee('Rp100.000,00');

    expect($kehadiran->fresh()->jam_keluar)->not->toBeNull();
    $this->assertDatabaseCount('honorarium', 0);
});

it('marks completed employee attendance paid once and stores a separate payment snapshot', function () {
    $admin = User::factory()->create(['role' => 'bendahara']);
    $pegawai = User::factory()->create([
        'name' => 'Pegawai Dibayar',
        'role' => 'staff',
    ]);
    KehadiranDosen::query()->create([
        'user_id' => $pegawai->id,
        'tanggal' => '2026-10-12',
        'jam_masuk' => '08:00:00',
        'jam_keluar' => '17:00:00',
        'status' => KehadiranDosen::STATUS_HADIR,
    ]);
    TarifHonorPegawai::query()->create([
        'user_id' => $pegawai->id,
        'nominal' => 125000.50,
        'berlaku_mulai' => '2026-10-01',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.honorarium.pegawai.pay', $pegawai), [
            'bulan' => 10,
            'tahun' => 2026,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('honorarium', [
        'user_id' => $pegawai->id,
        'nama_pegawai' => 'Pegawai Dibayar',
        'bulan' => 10,
        'tahun' => 2026,
        'total_kehadiran' => 1,
        'total_honor_mengajar' => 125000.50,
        'total_penerimaan' => 125000.50,
        'status_pembayaran' => Honorarium::STATUS_PAID,
        'dibayar_oleh' => $admin->id,
    ]);
    $this->actingAs($admin)
        ->patch(route('admin.honorarium.pegawai.pay', $pegawai), [
            'bulan' => 10,
            'tahun' => 2026,
        ])
        ->assertStatus(409);

    expect(Honorarium::query()->where('user_id', $pegawai->id)->count())->toBe(1);
});

it('does not pay employee attendance without a tariff for every completed attendance', function () {
    $bendahara = User::factory()->create(['role' => 'bendahara']);
    $pegawai = User::factory()->create(['role' => 'staff']);
    KehadiranDosen::query()->create([
        'user_id' => $pegawai->id,
        'tanggal' => '2026-10-12',
        'jam_masuk' => '08:00:00',
        'jam_keluar' => '17:00:00',
        'status' => KehadiranDosen::STATUS_HADIR,
    ]);

    $this->actingAs($bendahara)
        ->patch(route('admin.honorarium.pegawai.pay', $pegawai), [
            'bulan' => 10,
            'tahun' => 2026,
        ])
        ->assertSessionHasErrors('pegawai');

    $this->assertDatabaseMissing('honorarium', ['user_id' => $pegawai->id]);
});

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
