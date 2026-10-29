<?php

use App\Models\Dosen;
use App\Models\Honorarium;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('shows the bendahara dashboard with honorarium summary and chart data', function () {
    $this->withoutVite();
    $bendahara = User::factory()->create(['role' => 'bendahara']);
    $paidDosen = Dosen::factory()->create();
    $draftDosen = Dosen::factory()->create();

    Honorarium::create([
        'dosen_id' => $paidDosen->id,
        'bulan' => now()->month,
        'tahun' => now()->year,
        'total_penerimaan' => 75000,
        'status_pembayaran' => Honorarium::STATUS_PAID,
    ]);

    Honorarium::create([
        'dosen_id' => $draftDosen->id,
        'bulan' => now()->month,
        'tahun' => now()->year,
        'total_penerimaan' => 50000,
        'status_pembayaran' => Honorarium::STATUS_DRAFT,
    ]);

    $this->actingAs($bendahara)
        ->get(route('bendahara.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard Bendahara')
        ->assertSee('2')
        ->assertSee('Rp75.000')
        ->assertSee('Pengeluaran')
        ->assertSee('1 rekap')
        ->assertSee('honorariumChart')
        ->assertSee('attendanceChart')
        ->assertSee('Disetujui');
});
