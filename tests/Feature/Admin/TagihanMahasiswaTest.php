<?php

use App\Models\Fakultas;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\TagihanMahasiswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $fakultas = Fakultas::create(['nama' => 'Fakultas Uji']);
    $this->prodi = Prodi::create([
        'fakultas_id' => $fakultas->id,
        'nama' => 'Program Studi Uji',
    ]);
    $this->bendahara = User::factory()->create(['role' => 'bendahara']);
});

it('creates invoices for an angkatan and sends Fonnte messages', function () {
    config()->set('services.fonnte.token', 'test-token');

    Http::fake([
        'https://api.fonnte.com/send' => Http::response(['status' => true], 200),
    ]);

    $mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => $this->prodi->id,
        'angkatan' => '2026',
        'rombel' => 'A',
        'phone' => '081234567890',
    ]);

    $this->actingAs($this->bendahara)
        ->post(route('bendahara.tagihan.store'), [
            'target_type' => 'angkatan',
            'angkatan' => '2026',
            'nama' => 'SPP Semester Ganjil',
            'jumlah' => 2500000,
            'jatuh_tempo' => now()->addMonth()->toDateString(),
            'keterangan' => 'Pembayaran semester ganjil',
            'send_now' => '1',
        ])
        ->assertRedirect();

    $tagihan = TagihanMahasiswa::query()->where('mahasiswa_id', $mahasiswa->id)->firstOrFail();

    expect($tagihan->nama)->toBe('SPP Semester Ganjil')
        ->and($tagihan->status)->toBe(TagihanMahasiswa::STATUS_BELUM_BAYAR)
        ->and($tagihan->wa_sent_at)->not->toBeNull();

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'test-token')
        && $request['target'] === '6281234567890');

    $this->actingAs($mahasiswa->user)
        ->get(route('mahasiswa.tagihan.index'))
        ->assertOk()
        ->assertSee('SPP Semester Ganjil');
});

it('creates invoices for a single student and can mark it paid', function () {
    $mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => $this->prodi->id,
        'angkatan' => '2026',
        'phone' => null,
    ]);

    $this->actingAs($this->bendahara)
        ->post(route('bendahara.tagihan.store'), [
            'target_type' => 'mahasiswa',
            'mahasiswa_id' => $mahasiswa->id,
            'nama' => 'Biaya Praktikum',
            'jumlah' => 500000,
            'jatuh_tempo' => now()->addDays(7)->toDateString(),
        ])
        ->assertRedirect();

    $tagihan = TagihanMahasiswa::query()->where('mahasiswa_id', $mahasiswa->id)->firstOrFail();

    $this->actingAs($this->bendahara)
        ->patch(route('bendahara.tagihan.paid', $tagihan))
        ->assertRedirect();

    expect($tagihan->fresh()->status)->toBe(TagihanMahasiswa::STATUS_SUDAH_BAYAR)
        ->and($tagihan->fresh()->paid_at)->not->toBeNull();
});
