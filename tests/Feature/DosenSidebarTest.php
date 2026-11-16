<?php

use App\Models\Dosen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('does not duplicate lecturer navigation for a program administrator lecturer', function () {
    $this->withoutVite();

    $dosen = Dosen::factory()->create();
    $dosen->user->assignRole(
        Role::findOrCreate('dosen', 'web'),
        Role::findOrCreate('admin_prodi', 'web'),
    );

    $response = $this->actingAs($dosen->user)
        ->get(route('dosen.dashboard'))
        ->assertOk()
        ->assertSee('Data Mahasiswa Prodi');

    expect(substr_count($response->getContent(), '/dosen/bimbingan"'))->toBe(1)
        ->and(substr_count($response->getContent(), '/dosen/penilaian"'))->toBe(1);
});
