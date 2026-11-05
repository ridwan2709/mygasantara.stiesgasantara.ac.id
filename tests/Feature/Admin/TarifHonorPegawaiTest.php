<?php

use App\Models\TarifHonorPegawai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('shows eligible employees, current rates, and rate history', function () {
    $this->withoutVite();
    $bendahara = User::factory()->create(['role' => 'bendahara']);
    $pegawaiAktif = User::factory()->create([
        'name' => 'Pegawai Aktif',
        'role' => 'staff',
        'boleh_absen' => false,
    ]);
    $pegawaiBelumAktif = User::factory()->create([
        'name' => 'Dosen Tidak Dipilih',
        'role' => 'dosen',
        'boleh_absen' => true,
    ]);

    TarifHonorPegawai::query()->create([
        'user_id' => $pegawaiAktif->id,
        'nominal' => 150000,
        'berlaku_mulai' => today()->subMonth()->toDateString(),
        'dibuat_oleh' => $bendahara->id,
    ]);
    TarifHonorPegawai::query()->create([
        'user_id' => $pegawaiAktif->id,
        'nominal' => 125000,
        'berlaku_mulai' => today()->subMonths(3)->toDateString(),
        'berlaku_sampai' => today()->subMonth()->subDay()->toDateString(),
        'dibuat_oleh' => $bendahara->id,
    ]);

    $this->actingAs($bendahara)
        ->get(route('admin.honorarium.tarif-pegawai.index'))
        ->assertOk()
        ->assertSee('Tarif Honor Pegawai')
        ->assertSee(route('admin.honorarium.tarif-pegawai.index'))
        ->assertSee('Pegawai Aktif')
        ->assertDontSee('Dosen Tidak Dipilih')
        ->assertSee('150.000,00')
        ->assertSee('125.000,00');
});

it('stores a new employee rate and closes the previous rate the day before', function () {
    $bendahara = User::factory()->create(['role' => 'bendahara']);
    $pegawai = User::factory()->create(['role' => 'staff', 'boleh_absen' => false]);
    $tanggalMulaiLama = today()->subMonth()->toDateString();
    $tanggalMulaiBaru = today()->toDateString();

    $tarifLama = TarifHonorPegawai::query()->create([
        'user_id' => $pegawai->id,
        'nominal' => 125000,
        'berlaku_mulai' => $tanggalMulaiLama,
    ]);

    $this->actingAs($bendahara)
        ->post(route('admin.honorarium.tarif-pegawai.store'), [
            'user_id' => $pegawai->id,
            'nominal' => 175000.50,
            'berlaku_mulai' => $tanggalMulaiBaru,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($tarifLama->fresh()->berlaku_sampai->toDateString())
        ->toBe(today()->subDay()->toDateString());

    $this->assertDatabaseHas('tarif_honor_pegawai', [
        'user_id' => $pegawai->id,
        'dibuat_oleh' => $bendahara->id,
    ]);
    $tarifBaru = TarifHonorPegawai::query()->where('user_id', $pegawai->id)->latest('berlaku_mulai')->firstOrFail();
    expect($tarifBaru->nominal)->toBe('175000.50')
        ->and($tarifBaru->berlaku_mulai->toDateString())->toBe($tanggalMulaiBaru);
});

it('rejects rate dates that are not later than an existing employee rate', function () {
    $bendahara = User::factory()->create(['role' => 'superadmin']);
    $pegawai = User::factory()->create(['role' => 'staff', 'boleh_absen' => false]);
    $tanggalMulai = today()->subMonth()->toDateString();
    TarifHonorPegawai::query()->create([
        'user_id' => $pegawai->id,
        'nominal' => 125000,
        'berlaku_mulai' => $tanggalMulai,
    ]);

    $this->actingAs($bendahara)
        ->from(route('admin.honorarium.tarif-pegawai.index'))
        ->post(route('admin.honorarium.tarif-pegawai.store'), [
            'user_id' => $pegawai->id,
            'nominal' => 150000,
            'berlaku_mulai' => $tanggalMulai,
        ])
        ->assertSessionHasErrors('berlaku_mulai');

    $pegawaiDenganTarifMendatang = User::factory()->create(['role' => 'staff', 'boleh_absen' => false]);
    TarifHonorPegawai::query()->create([
        'user_id' => $pegawaiDenganTarifMendatang->id,
        'nominal' => 175000,
        'berlaku_mulai' => today()->addMonth()->toDateString(),
    ]);

    $this->actingAs($bendahara)
        ->from(route('admin.honorarium.tarif-pegawai.index'))
        ->post(route('admin.honorarium.tarif-pegawai.store'), [
            'user_id' => $pegawaiDenganTarifMendatang->id,
            'nominal' => 150000,
            'berlaku_mulai' => today()->toDateString(),
        ])
        ->assertSessionHasErrors('berlaku_mulai');

    $this->assertDatabaseCount('tarif_honor_pegawai', 2);
});

it('rejects users outside the employee roles', function () {
    $bendahara = User::factory()->create(['role' => 'bendahara']);
    $user = User::factory()->create(['role' => 'dosen', 'boleh_absen' => true]);

    $this->actingAs($bendahara)
        ->from(route('admin.honorarium.tarif-pegawai.index'))
        ->post(route('admin.honorarium.tarif-pegawai.store'), [
            'user_id' => $user->id,
            'nominal' => 150000,
            'berlaku_mulai' => today()->toDateString(),
        ])
        ->assertSessionHasErrors('user_id');

    $this->assertDatabaseCount('tarif_honor_pegawai', 0);
});

it('does not allow roles outside superadmin and bendahara to manage employee rates', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $this->actingAs($user)
        ->get(route('admin.honorarium.tarif-pegawai.index'))
        ->assertForbidden();
});
