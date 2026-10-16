<?php

use App\Models\Fakultas;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('imports faculty data from a csv file', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $file = UploadedFile::fake()->createWithContent('fakultas.csv', "nama\nFakultas Teknik\n");

    $this->actingAs($admin)
        ->post(route('admin.master-data.import', 'fakultas'), ['file' => $file])
        ->assertRedirect(route('admin.fakultas.index'))
        ->assertSessionHas('import_success', '1 data Fakultas berhasil diimpor.');

    $this->assertDatabaseHas('fakultas', ['nama' => 'Fakultas Teknik']);
});

it('rejects the whole file when duplicate data is present', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    Fakultas::create(['nama' => 'Fakultas Lama']);
    $file = UploadedFile::fake()->createWithContent('fakultas.csv', "nama\nFakultas Baru\nFakultas Lama\n");

    $this->actingAs($admin)
        ->post(route('admin.master-data.import', 'fakultas'), ['file' => $file])
        ->assertRedirect(route('admin.fakultas.index'))
        ->assertSessionHas('import_failure', 'Impor gagal. Tidak ada data dari file ini yang disimpan. Perbaiki baris dan kolom berikut, lalu unggah kembali.')
        ->assertSessionHasErrorsIn('masterDataImport', 'import_file');

    $this->assertDatabaseCount('fakultas', 1);
    $this->assertDatabaseMissing('fakultas', ['nama' => 'Fakultas Baru']);
});

it('shows a row and field reason when imported data is invalid', function () {
    Fakultas::create(['nama' => 'Fakultas Teknik']);
    $admin = User::factory()->create(['role' => 'superadmin']);
    $file = UploadedFile::fake()->createWithContent('prodi.csv', "fakultas,nama\nFakultas Teknik,\n");

    $this->actingAs($admin)
        ->post(route('admin.master-data.import', 'prodi'), ['file' => $file])
        ->assertRedirect(route('admin.prodi.index'))
        ->assertSessionHas('import_failure')
        ->assertSessionHasErrorsIn('masterDataImport', 'import_file');

    $errors = session('errors')->getBag('masterDataImport')->get('import_file');
    expect($errors)->toContain('Baris 2: Kolom Nama: wajib diisi.');
});

it('shows a clear message when the uploaded file is not csv', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $file = UploadedFile::fake()->createWithContent('data.xlsx', 'not a csv');

    $this->actingAs($admin)
        ->post(route('admin.master-data.import', 'fakultas'), ['file' => $file])
        ->assertRedirect(route('admin.fakultas.index'))
        ->assertSessionHas('import_failure', 'File tidak dapat diimpor. Perbaiki masalah berikut, lalu unggah kembali.')
        ->assertSessionHasErrorsIn('masterDataImport', 'file');
});

it('keeps faculty administrators from importing prodi into another faculty', function () {
    $fakultas = Fakultas::create(['nama' => 'Fakultas Teknik']);
    Fakultas::create(['nama' => 'Fakultas Ekonomi']);
    $admin = User::factory()->create([
        'role' => 'admin_fakultas',
        'fakultas_id' => $fakultas->id,
    ]);
    $file = UploadedFile::fake()->createWithContent('prodi.csv', "fakultas,nama\nFakultas Ekonomi,Akuntansi\n");

    $this->actingAs($admin)
        ->post(route('admin.master-data.import', 'prodi'), ['file' => $file])
        ->assertRedirect(route('admin.prodi.index'));

    $this->assertDatabaseMissing('prodi', ['nama' => 'Akuntansi']);
});

it('creates student login accounts with hashed imported passwords', function () {
    $fakultas = Fakultas::create(['nama' => 'Fakultas Teknik']);
    $prodi = Prodi::create(['nama' => 'Teknik Informatika', 'fakultas_id' => $fakultas->id]);
    $admin = User::factory()->create(['role' => 'superadmin']);
    $csv = implode(',', ['nim', 'nama', 'email', 'password', 'prodi', 'angkatan', 'dosen_pa_nidn', 'status'])
        ."\n".implode(',', ['2026001', 'Mahasiswa Baru', 'mhs@example.test', 'password-awal', 'Teknik Informatika', '2026', '', 'aktif'])."\n";
    $file = UploadedFile::fake()->createWithContent('mahasiswa.csv', $csv);

    $this->actingAs($admin)
        ->post(route('admin.master-data.import', 'mahasiswa'), ['file' => $file])
        ->assertRedirect(route('admin.mahasiswa.index'));

    $student = Mahasiswa::with('user')->where('nim', '2026001')->firstOrFail();
    expect($student->prodi_id)->toBe($prodi->id)
        ->and(Hash::check('password-awal', $student->user->password))->toBeTrue();
});

it('downloads a csv template with the expected headers', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);

    $response = $this->actingAs($admin)
        ->get(route('admin.master-data.template', 'mata-kuliah'));

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $content = $response->streamedContent();
    expect($content)
        ->toContain('kode_mk,nama_mk,sks,semester,prodi,penanda_contoh')
        ->toContain('CONTOH-MK-001')
        ->toContain('CONTOH-MK-002')
        ->toContain('CONTOH,');
});

it('includes two sample rows in every master data template', function (string $type, string $firstExample, string $secondExample) {
    $admin = User::factory()->create(['role' => 'superadmin']);

    $response = $this->actingAs($admin)->get(route('admin.master-data.template', $type));

    $response->assertOk();
    $content = $response->streamedContent();
    expect($content)->toContain($firstExample)->toContain($secondExample);
})->with([
    'fakultas' => ['fakultas', 'Fakultas Contoh 1', 'Fakultas Contoh 2'],
    'prodi' => ['prodi', 'Program Studi Contoh 1', 'Program Studi Contoh 2'],
    'mahasiswa' => ['mahasiswa', 'CONTOH001', 'CONTOH002'],
    'dosen' => ['dosen', 'NIDN-CONTOH-001', 'NIDN-CONTOH-002'],
    'mata kuliah' => ['mata-kuliah', 'CONTOH-MK-001', 'CONTOH-MK-002'],
    'kelas' => ['kelas', 'KODE_MK_TERDAFTAR', 'NIDN_TERDAFTAR'],
    'tahun akademik' => ['tahun-akademik', '2026/2027,ganjil', '2026/2027,genap'],
    'ruangan' => ['ruangan', 'R-CONTOH-01', 'R-CONTOH-02'],
]);

it('ignores marked sample rows when importing a template', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $file = UploadedFile::fake()->createWithContent(
        'fakultas.csv',
        "nama,penanda_contoh\nFakultas Contoh 1,CONTOH\nFakultas Contoh 2,CONTOH\nFakultas Aktual,\n"
    );

    $this->actingAs($admin)
        ->post(route('admin.master-data.import', 'fakultas'), ['file' => $file])
        ->assertRedirect(route('admin.fakultas.index'))
        ->assertSessionHas('import_success', '1 data Fakultas berhasil diimpor.');

    $this->assertDatabaseCount('fakultas', 1);
    $this->assertDatabaseHas('fakultas', ['nama' => 'Fakultas Aktual']);
});
