<?php

use App\Models\KehadiranDosen;
use App\Models\PengaturanGpsKehadiran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatPengaturanGpsPegawai(): PengaturanGpsKehadiran
{
    return PengaturanGpsKehadiran::query()->create([
        'latitude' => -6.2,
        'longitude' => 106.8166667,
        'radius_meter' => 100,
    ]);
}

it('allows designated roles to view employee attendance without an attendance flag', function () {
    $this->withoutVite();
    $user = User::factory()->create([
        'role' => 'staff',
        'boleh_absen' => false,
    ]);

    $this->actingAs($user)
        ->get(route('pegawai.kehadiran.index'))
        ->assertOk()
        ->assertSee('Absen')
        ->assertSee('Belum absen')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');
});

it('denies attendance to roles outside the employee attendance roles even if flagged', function () {
    $user = User::factory()->create([
        'role' => 'operator',
        'boleh_absen' => true,
    ]);

    $this->actingAs($user)
        ->get(route('pegawai.kehadiran.index'))
        ->assertForbidden();
});

it('ignores the legacy attendance flag in user management', function () {
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $user = User::factory()->create([
        'role' => 'staff',
        'boleh_absen' => true,
    ]);

    $this->actingAs($superadmin)
        ->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'fakultas_id' => null,
            'boleh_absen' => '0',
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->boleh_absen)->toBeTrue();
});

it('renders employee attendance safely in administrator monitoring', function () {
    $this->withoutVite();
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $user = User::factory()->create([
        'name' => 'Pegawai Penguji',
        'role' => 'staff',
        'boleh_absen' => true,
    ]);
    KehadiranDosen::query()->create([
        'user_id' => $user->id,
        'tanggal' => now()->toDateString(),
        'status' => KehadiranDosen::STATUS_HADIR,
        'jam_masuk' => now()->format('H:i:s'),
    ]);

    $this->actingAs($superadmin)
        ->get(route('admin.kehadiran-dosen.index'))
        ->assertOk()
        ->assertSee('Pegawai Penguji')
        ->assertSee('Mata Kuliah')
        ->assertSee('Monitoring Kehadiran Dosen');
});

it('records a gps-validated employee check-in without creating teaching attendance', function () {
    $this->withoutVite();
    $user = User::factory()->create([
        'role' => 'staff',
        'boleh_absen' => true,
    ]);
    $otherUser = User::factory()->create();
    buatPengaturanGpsPegawai();

    $this->actingAs($user)
        ->post(route('pegawai.kehadiran.masuk'), [
            'user_id' => $otherUser->id,
            'latitude' => -6.2001,
            'longitude' => 106.8167,
            'keterangan' => 'Hadir di kantor',
        ])
        ->assertRedirect(route('pegawai.kehadiran.index'))
        ->assertSessionHas('success');

    $kehadiran = KehadiranDosen::query()->firstOrFail();
    expect($kehadiran->user_id)->toBe($user->id)
        ->and($kehadiran->dosen_id)->toBeNull()
        ->and($kehadiran->jadwal_kuliah_id)->toBeNull()
        ->and($kehadiran->isPegawai())->toBeTrue();

    $this->assertDatabaseCount('kehadiran_mengajar', 0);
    $this->assertDatabaseHas('kehadiran_dosen', [
        'user_id' => $user->id,
        'status' => KehadiranDosen::STATUS_HADIR,
        'keterangan' => 'Hadir di kantor',
    ]);
});

it('rejects duplicate check-ins for the same day', function () {
    $user = User::factory()->create([
        'role' => 'staff',
        'boleh_absen' => true,
    ]);
    buatPengaturanGpsPegawai();
    KehadiranDosen::query()->create([
        'user_id' => $user->id,
        'tanggal' => now()->toDateString(),
        'status' => KehadiranDosen::STATUS_HADIR,
        'jam_masuk' => now()->format('H:i:s'),
    ]);

    $this->actingAs($user)
        ->from(route('pegawai.kehadiran.index'))
        ->post(route('pegawai.kehadiran.masuk'), [
            'latitude' => -6.2001,
            'longitude' => 106.8167,
        ])
        ->assertRedirect(route('pegawai.kehadiran.index'))
        ->assertSessionHasErrors(['absen' => 'Anda sudah absen masuk hari ini.']);

    $this->assertDatabaseCount('kehadiran_dosen', 1);
});

it('requires a valid gps location before check-in', function () {
    $user = User::factory()->create([
        'role' => 'staff',
        'boleh_absen' => true,
    ]);
    buatPengaturanGpsPegawai();

    $this->actingAs($user)
        ->post(route('pegawai.kehadiran.masuk'), [
            'latitude' => -6.21,
            'longitude' => 106.8167,
        ])
        ->assertSessionHasErrors('gps');

    $this->assertDatabaseCount('kehadiran_dosen', 0);
});

it('records checkout without gps after a check-in', function () {
    $user = User::factory()->create([
        'role' => 'staff',
        'boleh_absen' => true,
    ]);
    $kehadiran = KehadiranDosen::query()->create([
        'user_id' => $user->id,
        'tanggal' => now()->toDateString(),
        'status' => KehadiranDosen::STATUS_HADIR,
        'jam_masuk' => now()->subHour()->format('H:i:s'),
    ]);

    $this->actingAs($user)
        ->post(route('pegawai.kehadiran.keluar'))
        ->assertRedirect(route('pegawai.kehadiran.index'))
        ->assertSessionHas('success');

    expect($kehadiran->fresh()->jam_keluar)->toBe(now()->format('H:i:s'));
    $this->assertDatabaseCount('kehadiran_mengajar', 0);
});

it('denies lecturer checkout for employee attendance records', function () {
    $user = User::factory()->create([
        'role' => 'staff',
        'boleh_absen' => true,
    ]);
    $kehadiran = KehadiranDosen::query()->create([
        'user_id' => $user->id,
        'tanggal' => now()->toDateString(),
        'status' => KehadiranDosen::STATUS_HADIR,
        'jam_masuk' => now()->format('H:i:s'),
    ]);

    $this->actingAs($user)
        ->post(route('dosen.kehadiran.checkout', $kehadiran))
        ->assertForbidden();
});

it('rejects checkout when no check-in exists or checkout was already recorded', function () {
    $user = User::factory()->create([
        'role' => 'staff',
        'boleh_absen' => true,
    ]);

    $this->actingAs($user)
        ->from(route('pegawai.kehadiran.index'))
        ->post(route('pegawai.kehadiran.keluar'))
        ->assertSessionHasErrors('absen');

    KehadiranDosen::query()->create([
        'user_id' => $user->id,
        'tanggal' => now()->toDateString(),
        'status' => KehadiranDosen::STATUS_HADIR,
        'jam_masuk' => now()->subHour()->format('H:i:s'),
        'jam_keluar' => now()->format('H:i:s'),
    ]);

    $this->actingAs($user)
        ->from(route('pegawai.kehadiran.index'))
        ->post(route('pegawai.kehadiran.keluar'))
        ->assertSessionHasErrors(['absen' => 'Anda sudah absen keluar hari ini.']);
});
