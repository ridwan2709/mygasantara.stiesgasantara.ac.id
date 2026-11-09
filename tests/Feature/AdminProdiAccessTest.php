<?php

use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('logs admin prodi accounts into the admin dashboard', function () {
    $prodi = Prodi::factory()->create();
    $admin = User::factory()->create([
        'role' => 'admin_prodi',
        'prodi_id' => $prodi->id,
    ]);

    $this->post(route('login'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard', absolute: false));

    $this->get(route('admin.dashboard'))->assertOk();
});

it('shows admin fakultas menus to admin prodi and scopes them to its program', function () {
    $prodi = Prodi::factory()->create();
    $otherProdi = Prodi::factory()->create();
    $admin = User::factory()->create([
        'role' => 'admin_prodi',
        'prodi_id' => $prodi->id,
    ]);
    $student = Mahasiswa::factory()->create(['prodi_id' => $prodi->id]);
    $otherStudent = Mahasiswa::factory()->create(['prodi_id' => $otherProdi->id]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Mahasiswa Bimbingan')
        ->assertSee('Input Nilai')
        ->assertSee('Data Mahasiswa Prodi');

    $this->get(route('dosen.bimbingan.index'))
        ->assertOk()
        ->assertSee($student->nim)
        ->assertDontSee($otherStudent->nim);
});

it('hides the moved admin menus from legacy admin fakultas accounts', function () {
    $fakultas = Fakultas::factory()->create();
    $admin = User::factory()->create([
        'role' => 'admin_fakultas',
        'fakultas_id' => $fakultas->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('Admin Panel')
        ->assertDontSee('Mahasiswa Bimbingan')
        ->assertDontSee('Data Mahasiswa Prodi');
});

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

it('allows a lecturer to also have admin prodi access', function () {
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $prodi = Prodi::factory()->create();

    $this->actingAs($superadmin)
        ->post(route('admin.users.store'), [
            'name' => 'Dosen sekaligus Admin Prodi',
            'email' => 'dosen.adminprodi@example.test',
            'password' => 'password-123',
            'role' => 'dosen',
            'nidn' => '0099887766',
            'prodi_id' => $prodi->id,
            'is_admin_prodi' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $user = User::where('email', 'dosen.adminprodi@example.test')->firstOrFail();

    expect($user->role)->toBe('dosen')
        ->and($user->isAdminProdi())->toBeTrue()
        ->and($user->dosen)->toBeInstanceOf(Dosen::class)
        ->and($user->dosen->prodi_id)->toBe($prodi->id);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Admin Panel')
        ->assertSee('Input Nilai');

    $this->get(route('dosen.lms.index'))->assertOk();
});

it('allows admin prodi access to be added to and removed from a lecturer', function () {
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $dosen = Dosen::factory()->create();
    $prodi = $dosen->prodi;
    $user = $dosen->user;
    $userData = [
        'name' => $user->name,
        'email' => $user->email,
        'role' => 'dosen',
        'prodi_id' => $prodi->id,
    ];

    $this->actingAs($superadmin)
        ->put(route('admin.users.update', $user), [
            ...$userData,
            'is_admin_prodi' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($user->fresh()->isAdminProdi())->toBeTrue();

    $this->put(route('admin.users.update', $user), $userData)
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($user->fresh()->isAdminProdi())->toBeFalse();
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

it('renders functional edit buttons for users with safely escaped details', function () {
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $user = User::factory()->create([
        'name' => 'Admin "Program"',
        'role' => 'admin_prodi',
    ]);

    $this->actingAs($superadmin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('onclick="openEditModal(this)"', false)
        ->assertSee('data-user-id="'.$user->id.'"', false)
        ->assertSee('data-user-name="Admin &quot;Program&quot;"', false)
        ->assertSee('data-user-email="'.$user->email.'"', false)
        ->assertSee('data-user-role="admin_prodi"', false);
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
