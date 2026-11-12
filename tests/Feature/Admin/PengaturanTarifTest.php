<?php

use App\Models\Honorarium;
use App\Models\KehadiranDosen;
use App\Models\TarifHonorarium;
use App\Models\TarifHonorPegawai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows admins and bendahara to configure lecturer rates and employee rates per role or user', function () {
    $this->withoutVite();

    $admin = User::factory()->create(['role' => 'superadmin']);
    $bendahara = User::factory()->create(['role' => 'bendahara']);
    $userTariffEmployee = User::factory()->create(['name' => 'Pegawai Tarif User', 'role' => 'staff']);
    $roleTariffEmployee = User::factory()->create(['name' => 'Pegawai Tarif Role', 'role' => 'staff']);
    $tarifMulai = today()->startOfMonth()->toDateString();

    $this->actingAs($admin)
        ->get(route('admin.pengaturan-tarif.index'))
        ->assertOk()
        ->assertSee('Pengaturan Tarif')
        ->assertSee('Atur tarif per role')
        ->assertSee('Atur tarif per user');

    $this->actingAs($bendahara)
        ->get(route('admin.pengaturan-tarif.index'))
        ->assertOk()
        ->assertSee('Pengaturan Tarif')
        ->assertSee('Tarif Dosen');

    $this->actingAs($bendahara)
        ->post(route('admin.pengaturan-tarif.dosen.store'), [
            'basis_tarif' => 'per_sks',
            'nominal' => 50000,
            'berlaku_mulai' => today()->toDateString(),
        ])
        ->assertRedirect();

    expect(TarifHonorarium::query()->where('basis_tarif', 'per_sks')->value('nominal'))->toEqual('50000.00');

    $this->post(route('admin.pengaturan-tarif.pegawai.store'), [
        'target_type' => 'role',
        'role' => 'staff',
        'nominal' => 80000,
        'berlaku_mulai' => $tarifMulai,
    ])->assertRedirect();

    $this->post(route('admin.pengaturan-tarif.pegawai.store'), [
        'target_type' => 'user',
        'user_id' => $userTariffEmployee->id,
        'nominal' => 120000,
        'berlaku_mulai' => $tarifMulai,
    ])->assertRedirect();

    expect(TarifHonorPegawai::query()->where('role', 'staff')->value('nominal'))->toEqual('80000.00')
        ->and(TarifHonorPegawai::query()->where('user_id', $userTariffEmployee->id)->value('nominal'))->toEqual('120000.00');

    foreach ([[$userTariffEmployee, 2], [$roleTariffEmployee, 3]] as [$pegawai, $jumlahHariHadir]) {
        for ($hari = 1; $hari <= $jumlahHariHadir; $hari++) {
            KehadiranDosen::create([
                'user_id' => $pegawai->id,
                'dosen_id' => null,
                'tanggal' => today()->startOfMonth()->addDays($hari - 1)->toDateString(),
                'jam_masuk' => '08:00:00',
                'jam_keluar' => '16:00:00',
                'status' => KehadiranDosen::STATUS_HADIR,
            ]);
        }
    }

    foreach ([$userTariffEmployee, $roleTariffEmployee] as $pegawai) {
        $this->patch(route('admin.honorarium.pegawai.pay', $pegawai), [
            'bulan' => today()->month,
            'tahun' => today()->year,
        ])->assertRedirect();
    }

    $userTariffPayment = Honorarium::query()->where('user_id', $userTariffEmployee->id)->firstOrFail();
    $roleTariffPayment = Honorarium::query()->where('user_id', $roleTariffEmployee->id)->firstOrFail();

    expect($userTariffPayment->total_honor_mengajar)->toEqual('120000.00')
        ->and($userTariffPayment->total_kehadiran)->toBe(2)
        ->and($roleTariffPayment->total_honor_mengajar)->toEqual('80000.00')
        ->and($roleTariffPayment->total_kehadiran)->toBe(3);
});
