<?php

use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows faculty admins to manage only students in their faculty', function () {
    $this->withoutVite();

    $fakultas = Fakultas::factory()->create();
    $admin = User::factory()->create([
        'role' => 'admin_fakultas',
        'fakultas_id' => $fakultas->id,
    ]);
    $prodi = Prodi::factory()->create(['fakultas_id' => $fakultas->id]);
    $dosen = Dosen::factory()->create(['prodi_id' => $prodi->id]);
    $mahasiswa = Mahasiswa::factory()->create(['prodi_id' => $prodi->id]);

    $prodiLuar = Prodi::factory()->create();
    $mahasiswaLuar = Mahasiswa::factory()->create(['prodi_id' => $prodiLuar->id]);

    $this->actingAs($admin)
        ->get(route('admin.mahasiswa.index'))
        ->assertOk()
        ->assertSee($mahasiswa->user->name)
        ->assertDontSee($mahasiswaLuar->user->name)
        ->assertSee($prodi->nama)
        ->assertDontSee($prodiLuar->nama)
        ->assertSee($dosen->user->name);

    $this->actingAs($admin)
        ->post(route('admin.mahasiswa.store'), [
            'name' => 'Mahasiswa Baru Fakultas',
            'email' => 'mahasiswa.baru@example.test',
            'password' => 'password-rahasia',
            'nim' => 'NIM-BARU-FAKULTAS',
            'prodi_id' => $prodi->id,
            'angkatan' => 2026,
            'dosen_pa_id' => $dosen->id,
        ])
        ->assertRedirect();

    $mahasiswaBaru = Mahasiswa::where('nim', 'NIM-BARU-FAKULTAS')->firstOrFail();
    expect($mahasiswaBaru->prodi_id)->toBe($prodi->id)
        ->and($mahasiswaBaru->dosen_pa_id)->toBe($dosen->id);

    $this->actingAs($admin)
        ->put(route('admin.mahasiswa.update', $mahasiswa), [
            'name' => 'Mahasiswa Diubah',
            'email' => $mahasiswa->user->email,
            'nim' => $mahasiswa->nim,
            'prodi_id' => $prodi->id,
            'angkatan' => $mahasiswa->angkatan,
            'dosen_pa_id' => $dosen->id,
            'status' => 'aktif',
        ])
        ->assertRedirect();

    expect($mahasiswa->fresh()->user->name)->toBe('Mahasiswa Diubah');

    $this->actingAs($admin)
        ->post(route('admin.mahasiswa.store'), [
            'name' => 'Mahasiswa Prodi Luar',
            'email' => 'mahasiswa.luar@example.test',
            'password' => 'password-rahasia',
            'nim' => 'NIM-PRODI-LUAR',
            'prodi_id' => $prodiLuar->id,
            'angkatan' => 2026,
        ])
        ->assertForbidden();

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($admin)
        ->get(route('admin.mahasiswa.show', $mahasiswaLuar)))
        ->toThrow(ModelNotFoundException::class);

    expect(fn () => $this->actingAs($admin)
        ->put(route('admin.mahasiswa.update', $mahasiswaLuar), [
            'name' => 'Tidak boleh diubah',
            'email' => $mahasiswaLuar->user->email,
            'nim' => $mahasiswaLuar->nim,
            'prodi_id' => $prodiLuar->id,
            'angkatan' => $mahasiswaLuar->angkatan,
            'status' => 'aktif',
        ]))
        ->toThrow(ModelNotFoundException::class);

    expect(fn () => $this->actingAs($admin)
        ->delete(route('admin.mahasiswa.destroy', $mahasiswaLuar)))
        ->toThrow(ModelNotFoundException::class);

    $export = $this->actingAs($admin)
        ->get(route('admin.mahasiswa.export'))
        ->assertOk();
    $content = $export->streamedContent();

    expect(str_contains($content, $mahasiswa->nim))->toBeTrue();
    expect(str_contains($content, $mahasiswaLuar->nim))->toBeFalse();
});
