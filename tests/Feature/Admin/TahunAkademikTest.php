<?php

use App\Models\TahunAkademik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('redirects to the academic year list after activation', function () {
    $this->withoutVite();

    $admin = User::factory()->create(['role' => 'superadmin']);
    $previousTahunAkademik = TahunAkademik::create([
        'tahun' => '2025/2026',
        'semester' => 'genap',
        'is_active' => true,
    ]);
    $newTahunAkademik = TahunAkademik::create([
        'tahun' => '2026/2027',
        'semester' => 'ganjil',
        'is_active' => false,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tahun-akademik.activate', $newTahunAkademik))
        ->assertRedirect(route('admin.tahun-akademik.index'))
        ->assertSessionHas('success', 'Tahun akademik berhasil diaktifkan.');

    expect($newTahunAkademik->fresh()->is_active)->toBeTrue()
        ->and($previousTahunAkademik->fresh()->is_active)->toBeFalse();
});
