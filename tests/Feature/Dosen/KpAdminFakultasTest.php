<?php

use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\KerjaPraktek;
use App\Models\LogbookKp;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows faculty admins to manage KP within their faculty only', function () {
    $this->withoutVite();

    $fakultas = Fakultas::factory()->create();
    $admin = User::factory()->create([
        'role' => 'admin_fakultas',
        'fakultas_id' => $fakultas->id,
    ]);
    $mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => Prodi::factory()->create(['fakultas_id' => $fakultas->id])->id,
    ]);
    $kp = KerjaPraktek::create([
        'mahasiswa_id' => $mahasiswa->id,
        'pembimbing_id' => Dosen::factory()->create()->id,
        'nama_perusahaan' => 'Perusahaan Fakultas',
        'tanggal_mulai' => '2026-10-01',
        'tanggal_selesai' => '2026-12-01',
        'status' => KerjaPraktek::STATUS_BERLANGSUNG,
    ]);
    $logbook = LogbookKp::create([
        'kerja_praktek_id' => $kp->id,
        'tanggal' => '2026-11-06',
        'kegiatan' => 'Mengerjakan laporan',
        'status' => LogbookKp::STATUS_PENDING,
    ]);

    $mahasiswaLuarFakultas = Mahasiswa::factory()->create();
    $kpLuarFakultas = KerjaPraktek::create([
        'mahasiswa_id' => $mahasiswaLuarFakultas->id,
        'pembimbing_id' => Dosen::factory()->create()->id,
        'nama_perusahaan' => 'Perusahaan Fakultas Lain',
        'tanggal_mulai' => '2026-10-01',
        'tanggal_selesai' => '2026-12-01',
        'status' => KerjaPraktek::STATUS_BERLANGSUNG,
    ]);
    $logbookLuarFakultas = LogbookKp::create([
        'kerja_praktek_id' => $kpLuarFakultas->id,
        'tanggal' => '2026-11-06',
        'kegiatan' => 'Kegiatan di luar fakultas',
        'status' => LogbookKp::STATUS_PENDING,
    ]);

    $this->actingAs($admin)
        ->get(route('dosen.kp.index'))
        ->assertOk()
        ->assertSee('Perusahaan Fakultas')
        ->assertDontSee('Perusahaan Fakultas Lain');

    $this->actingAs($admin)
        ->get(route('dosen.kp.show', $kp))
        ->assertOk()
        ->assertSee($mahasiswa->user->name);

    $this->actingAs($admin)
        ->post(route('dosen.kp.logbook.review', $logbook), [
            'catatan_pembimbing' => 'Laporan dapat dilanjutkan.',
            'status' => LogbookKp::STATUS_DISETUJUI,
        ])
        ->assertRedirect();

    expect($logbook->fresh()->status)->toBe(LogbookKp::STATUS_DISETUJUI);

    $this->actingAs($admin)
        ->put(route('dosen.kp.update-status', $kp), [
            'status' => KerjaPraktek::STATUS_SELESAI_KP,
        ])
        ->assertRedirect();

    expect($kp->fresh()->status)->toBe(KerjaPraktek::STATUS_SELESAI_KP);

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($admin)
        ->get(route('dosen.kp.show', $kpLuarFakultas)))
        ->toThrow(HttpException::class);

    expect(fn () => $this->actingAs($admin)
        ->post(route('dosen.kp.logbook.review', $logbookLuarFakultas), [
            'catatan_pembimbing' => 'Tidak boleh lintas fakultas.',
            'status' => LogbookKp::STATUS_REVISI,
        ]))
        ->toThrow(HttpException::class);
});

it('preserves lecturer access to their assigned KP', function () {
    $this->withoutVite();

    $dosen = Dosen::factory()->create();
    $kp = KerjaPraktek::create([
        'mahasiswa_id' => Mahasiswa::factory()->create()->id,
        'pembimbing_id' => $dosen->id,
        'nama_perusahaan' => 'Perusahaan Dosen',
        'tanggal_mulai' => '2026-10-01',
        'tanggal_selesai' => '2026-12-01',
        'status' => KerjaPraktek::STATUS_BERLANGSUNG,
    ]);

    $this->actingAs($dosen->user)
        ->get(route('dosen.kp.index'))
        ->assertOk()
        ->assertSee('Perusahaan Dosen');

    $this->actingAs($dosen->user)
        ->get(route('dosen.kp.show', $kp))
        ->assertOk();
});
