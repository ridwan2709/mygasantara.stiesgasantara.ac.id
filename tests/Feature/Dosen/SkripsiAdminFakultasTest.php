<?php

use App\Models\BimbinganSkripsi;
use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Skripsi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows faculty admins to access skripsi and review bimbingan within their faculty only', function () {
    $this->withoutVite();

    $fakultas = Fakultas::factory()->create();
    $admin = User::factory()->create([
        'role' => 'admin_fakultas',
        'fakultas_id' => $fakultas->id,
    ]);
    $mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => Prodi::factory()->create(['fakultas_id' => $fakultas->id])->id,
    ]);
    $skripsi = Skripsi::create([
        'mahasiswa_id' => $mahasiswa->id,
        'judul' => 'Skripsi Fakultas',
        'status' => Skripsi::STATUS_BIMBINGAN,
    ]);
    $dosen = Dosen::factory()->create([
        'prodi_id' => Prodi::factory()->create(['fakultas_id' => $fakultas->id])->id,
    ]);
    $bimbingan = BimbinganSkripsi::create([
        'skripsi_id' => $skripsi->id,
        'dosen_id' => $dosen->id,
        'tanggal_bimbingan' => '2026-11-06',
        'catatan_mahasiswa' => 'Progres skripsi',
        'status' => BimbinganSkripsi::STATUS_MENUNGGU,
    ]);

    $mahasiswaLuarFakultas = Mahasiswa::factory()->create();
    $skripsiLuarFakultas = Skripsi::create([
        'mahasiswa_id' => $mahasiswaLuarFakultas->id,
        'judul' => 'Skripsi Fakultas Lain',
        'status' => Skripsi::STATUS_BIMBINGAN,
    ]);
    $bimbinganLuarFakultas = BimbinganSkripsi::create([
        'skripsi_id' => $skripsiLuarFakultas->id,
        'dosen_id' => Dosen::factory()->create()->id,
        'tanggal_bimbingan' => '2026-11-06',
        'status' => BimbinganSkripsi::STATUS_MENUNGGU,
    ]);

    $this->actingAs($admin)
        ->get(route('dosen.skripsi.index'))
        ->assertOk()
        ->assertSee('Skripsi Fakultas')
        ->assertDontSee('Skripsi Fakultas Lain');

    $this->actingAs($admin)
        ->get(route('dosen.skripsi.show', $skripsi))
        ->assertOk()
        ->assertSee($mahasiswa->user->name);

    $this->actingAs($admin)
        ->post(route('dosen.skripsi.bimbingan.review', $bimbingan), [
            'catatan_dosen' => 'Lanjutkan penelitian.',
            'status' => BimbinganSkripsi::STATUS_DISETUJUI,
        ])
        ->assertRedirect();

    expect($bimbingan->fresh()->status)->toBe(BimbinganSkripsi::STATUS_DISETUJUI);

    $this->actingAs($admin)
        ->put(route('dosen.skripsi.update-status', $skripsi), [
            'status' => Skripsi::STATUS_SEMINAR_PROPOSAL,
        ])
        ->assertRedirect();

    expect($skripsi->fresh()->status)->toBe(Skripsi::STATUS_SEMINAR_PROPOSAL);

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($admin)
        ->get(route('dosen.skripsi.show', $skripsiLuarFakultas)))
        ->toThrow(HttpException::class);

    expect(fn () => $this->actingAs($admin)
        ->post(route('dosen.skripsi.bimbingan.review', $bimbinganLuarFakultas), [
            'catatan_dosen' => 'Tidak boleh lintas fakultas.',
            'status' => BimbinganSkripsi::STATUS_REVISI,
        ]))
        ->toThrow(HttpException::class);
});

it('preserves lecturer access to their assigned skripsi', function () {
    $this->withoutVite();

    $dosen = Dosen::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create();
    $skripsi = Skripsi::create([
        'mahasiswa_id' => $mahasiswa->id,
        'pembimbing1_id' => $dosen->id,
        'judul' => 'Skripsi Bimbingan Dosen',
        'status' => Skripsi::STATUS_BIMBINGAN,
    ]);

    $this->actingAs($dosen->user)
        ->get(route('dosen.skripsi.index'))
        ->assertOk()
        ->assertSee('Skripsi Bimbingan Dosen');

    $this->actingAs($dosen->user)
        ->get(route('dosen.skripsi.show', $skripsi))
        ->assertOk();
});
