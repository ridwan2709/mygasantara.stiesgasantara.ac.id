<?php

use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('blocks admin prodi accounts without an assigned program', function () {
    $admin = User::factory()->create(['role' => 'admin_prodi']);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

it('creates an admin prodi account with its program assigned', function () {
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $prodi = Prodi::factory()->create();

    $this->actingAs($superadmin)
        ->post(route('admin.users.store'), [
            'name' => 'Admin Prodi Baru',
            'email' => 'admin.prodi@example.test',
            'password' => 'password-123',
            'role' => 'admin_prodi',
            'prodi_id' => $prodi->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('users', [
        'email' => 'admin.prodi@example.test',
        'role' => 'admin_prodi',
        'fakultas_id' => $prodi->fakultas_id,
        'prodi_id' => $prodi->id,
    ]);
});

it('displays the admin prodi role and assigned program in user management', function () {
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $prodi = Prodi::factory()->create();
    User::factory()->create([
        'name' => 'Admin Program',
        'role' => 'admin_prodi',
        'fakultas_id' => $prodi->fakultas_id,
        'prodi_id' => $prodi->id,
    ]);

    $this->actingAs($superadmin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('Admin Prodi')
        ->assertSee($prodi->nama);
});

it('limits the student list to the assigned program', function () {
    $prodi = Prodi::factory()->create();
    $otherProdi = Prodi::factory()->create();
    $admin = User::factory()->create([
        'role' => 'admin_prodi',
        'prodi_id' => $prodi->id,
    ]);
    $student = Mahasiswa::factory()->create(['prodi_id' => $prodi->id]);
    $otherStudent = Mahasiswa::factory()->create(['prodi_id' => $otherProdi->id]);

    $this->actingAs($admin)
        ->get(route('admin.mahasiswa.index'))
        ->assertOk()
        ->assertSee($student->nim)
        ->assertDontSee($otherStudent->nim);
});

it('prevents admin prodi from changing a student in another program', function () {
    $prodi = Prodi::factory()->create();
    $otherProdi = Prodi::factory()->create();
    $admin = User::factory()->create([
        'role' => 'admin_prodi',
        'prodi_id' => $prodi->id,
    ]);
    $student = Mahasiswa::factory()->create(['prodi_id' => $otherProdi->id]);

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($admin)
        ->put(route('admin.mahasiswa.update', $student), [
            'name' => $student->user->name,
            'email' => $student->user->email,
            'nim' => $student->nim,
            'prodi_id' => $prodi->id,
            'angkatan' => $student->angkatan,
            'status' => $student->status,
        ]))
        ->toThrow(NotFoundHttpException::class);
});
