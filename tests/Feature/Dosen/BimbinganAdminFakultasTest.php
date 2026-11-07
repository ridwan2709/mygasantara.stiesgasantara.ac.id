<?php

use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\TahunAkademik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows faculty admins to access advising and KRS only within their faculty', function () {
    $this->withoutVite();

    $fakultas = Fakultas::factory()->create();
    $admin = User::factory()->create([
        'role' => 'admin_fakultas',
        'fakultas_id' => $fakultas->id,
    ]);

    $mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => Prodi::factory()->create(['fakultas_id' => $fakultas->id])->id,
    ]);
    $mahasiswaLuarFakultas = Mahasiswa::factory()->create();

    $tahunAkademik = TahunAkademik::factory()->create();
    $krs = Krs::create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => $tahunAkademik->id,
        'status' => 'pending',
    ]);
    $krsLuarFakultas = Krs::create([
        'mahasiswa_id' => $mahasiswaLuarFakultas->id,
        'tahun_akademik_id' => $tahunAkademik->id,
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->get(route('dosen.bimbingan.index'))
        ->assertOk()
        ->assertSee($mahasiswa->user->name)
        ->assertDontSee($mahasiswaLuarFakultas->user->name);

    $this->actingAs($admin)
        ->get(route('dosen.bimbingan.krs-approval'))
        ->assertOk()
        ->assertSee($mahasiswa->user->name)
        ->assertDontSee($mahasiswaLuarFakultas->user->name);

    $this->actingAs($admin)
        ->get(route('dosen.bimbingan.krs-show', $krs))
        ->assertOk()
        ->assertSee($mahasiswa->user->name);

    $this->actingAs($admin)
        ->post(route('dosen.bimbingan.krs-approve', $krs))
        ->assertRedirect(route('dosen.bimbingan.krs-approval'));

    expect($krs->fresh()->status)->toBe('approved');

    $this->actingAs($admin)
        ->post(route('dosen.bimbingan.krs-approve', $krsLuarFakultas))
        ->assertForbidden();

    expect($krsLuarFakultas->fresh()->status)->toBe('pending');
});

it('preserves lecturer access to their own advisees', function () {
    $this->withoutVite();

    $dosen = Dosen::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create([
        'dosen_pa_id' => $dosen->id,
    ]);

    $this->actingAs($dosen->user)
        ->get(route('dosen.bimbingan.index'))
        ->assertOk()
        ->assertSee($mahasiswa->user->name);
});
