<?php

use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\TahunAkademik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('shows approved student semester KHS records on the staff page', function () {
    $this->withoutVite();
    $staff = User::factory()->create(['role' => 'staff']);
    $mahasiswa = Mahasiswa::factory()->create([
        'nim' => '2026000123',
        'user_id' => User::factory()->create(['name' => 'Mahasiswa KHS'])->id,
    ]);
    $tahunAkademik = TahunAkademik::factory()->create([
        'tahun' => '2026/2027',
        'semester' => 'Ganjil',
    ]);
    Krs::query()->create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => $tahunAkademik->id,
        'status' => 'approved',
    ]);

    $this->actingAs($staff)
        ->get(route('staff.khs.index'))
        ->assertOk()
        ->assertSee('KHS Mahasiswa')
        ->assertSee('Mahasiswa KHS')
        ->assertSee('2026000123')
        ->assertSee('2026/2027')
        ->assertSee('Ganjil');
});

it('filters staff KHS records by student name or NIM and excludes unapproved KRS', function () {
    $this->withoutVite();
    $staff = User::factory()->create(['role' => 'staff']);
    $tahunAkademik = TahunAkademik::factory()->create();
    $mahasiswaDicari = Mahasiswa::factory()->create([
        'nim' => '2026000456',
        'user_id' => User::factory()->create(['name' => 'Nama Target KHS'])->id,
    ]);
    $mahasiswaLain = Mahasiswa::factory()->create([
        'nim' => '2026000789',
        'user_id' => User::factory()->create(['name' => 'Mahasiswa Lain'])->id,
    ]);

    Krs::query()->create([
        'mahasiswa_id' => $mahasiswaDicari->id,
        'tahun_akademik_id' => $tahunAkademik->id,
        'status' => 'approved',
    ]);
    Krs::query()->create([
        'mahasiswa_id' => $mahasiswaLain->id,
        'tahun_akademik_id' => TahunAkademik::factory()->create()->id,
        'status' => 'pending',
    ]);

    $this->actingAs($staff)
        ->get(route('staff.khs.index', ['search' => '2026000456']))
        ->assertOk()
        ->assertSee('Nama Target KHS')
        ->assertDontSee('Mahasiswa Lain');

    $this->actingAs($staff)
        ->get(route('staff.khs.index', ['search' => 'Nama Target']))
        ->assertOk()
        ->assertSee('2026000456')
        ->assertDontSee('2026000789');
});

it('restricts the staff KHS page to staff role', function () {
    $user = User::factory()->create(['role' => 'bendahara']);

    $this->actingAs($user)
        ->get(route('staff.khs.index'))
        ->assertForbidden();
});

it('shows the KHS quick action and count on staff dashboard', function () {
    $this->withoutVite();
    $staff = User::factory()->create(['role' => 'staff']);
    $mahasiswa = Mahasiswa::factory()->create();
    Krs::query()->create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => TahunAkademik::factory()->create()->id,
        'status' => 'approved',
    ]);

    $this->actingAs($staff)
        ->get(route('staff.dashboard'))
        ->assertOk()
        ->assertSee('KHS')
        ->assertSee(route('staff.khs.index'))
        ->assertSee('1');
});
