<?php

namespace App\Services;

use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\MataKuliah;
use App\Models\Prodi;
use App\Models\Ruangan;
use App\Models\TahunAkademik;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MasterDataImportService
{
    private const FIELD_LABELS = [
        'nama' => 'Nama',
        'fakultas' => 'Fakultas',
        'prodi' => 'Program Studi',
        'nim' => 'NIM',
        'nidn' => 'NIDN',
        'nama_mk' => 'Nama Mata Kuliah',
        'kode_mk' => 'Kode Mata Kuliah',
        'sks' => 'SKS',
        'semester' => 'Semester',
        'nama_kelas' => 'Nama Kelas',
        'kapasitas' => 'Kapasitas',
        'angkatan' => 'Angkatan',
        'email' => 'Email',
        'password' => 'Password',
        'status' => 'Status',
        'tahun' => 'Tahun Akademik',
        'tanggal_mulai' => 'Tanggal Mulai',
        'tanggal_selesai' => 'Tanggal Selesai',
        'tanggal_krs_mulai' => 'Tanggal Mulai KRS',
        'tanggal_krs_selesai' => 'Tanggal Selesai KRS',
        'hari' => 'Hari',
        'jam_mulai' => 'Jam Mulai',
        'jam_selesai' => 'Jam Selesai',
        'kode_ruangan' => 'Kode Ruangan',
        'nama_ruangan' => 'Nama Ruangan',
        'gedung' => 'Gedung',
        'lantai' => 'Lantai',
        'fasilitas' => 'Fasilitas',
        'is_active' => 'Status Aktif',
    ];

    private const HEADERS = [
        'fakultas' => ['nama'],
        'prodi' => ['fakultas', 'nama'],
        'mahasiswa' => ['nim', 'nama', 'email', 'password', 'prodi', 'angkatan', 'dosen_pa_nidn', 'status'],
        'dosen' => ['nidn', 'nama', 'email', 'password', 'prodi'],
        'mata-kuliah' => ['kode_mk', 'nama_mk', 'sks', 'semester', 'prodi'],
        'kelas' => ['kode_mk', 'nidn', 'nama_kelas', 'kapasitas', 'tahun', 'semester', 'hari', 'jam_mulai', 'jam_selesai', 'ruangan'],
        'tahun-akademik' => ['tahun', 'semester', 'tanggal_mulai', 'tanggal_selesai', 'tanggal_krs_mulai', 'tanggal_krs_selesai'],
        'ruangan' => ['kode_ruangan', 'nama_ruangan', 'kapasitas', 'gedung', 'lantai', 'fasilitas', 'is_active', 'fakultas'],
    ];

    public function headers(string $type): array
    {
        if (! isset(self::HEADERS[$type])) {
            abort(404);
        }

        return self::HEADERS[$type];
    }

    public function templateRows(string $type): array
    {
        if (! isset(self::HEADERS[$type])) {
            abort(404);
        }

        $rows = match ($type) {
            'fakultas' => [
                ['nama' => 'Fakultas Contoh 1'],
                ['nama' => 'Fakultas Contoh 2'],
            ],
            'prodi' => [
                ['fakultas' => 'Nama Fakultas Terdaftar', 'nama' => 'Program Studi Contoh 1'],
                ['fakultas' => 'Nama Fakultas Terdaftar', 'nama' => 'Program Studi Contoh 2'],
            ],
            'mahasiswa' => [
                ['nim' => 'CONTOH001', 'nama' => 'Mahasiswa Contoh 1', 'email' => 'mahasiswa1@example.test', 'password' => 'GantiPassword123', 'prodi' => 'Nama Prodi Terdaftar', 'angkatan' => (string) (date('Y') - 1), 'dosen_pa_nidn' => '', 'status' => 'aktif'],
                ['nim' => 'CONTOH002', 'nama' => 'Mahasiswa Contoh 2', 'email' => 'mahasiswa2@example.test', 'password' => 'GantiPassword123', 'prodi' => 'Nama Prodi Terdaftar', 'angkatan' => (string) (date('Y') - 1), 'dosen_pa_nidn' => '', 'status' => 'aktif'],
            ],
            'dosen' => [
                ['nidn' => 'NIDN-CONTOH-001', 'nama' => 'Dosen Contoh 1', 'email' => 'dosen1@example.test', 'password' => 'GantiPassword123', 'prodi' => 'Nama Prodi Terdaftar'],
                ['nidn' => 'NIDN-CONTOH-002', 'nama' => 'Dosen Contoh 2', 'email' => 'dosen2@example.test', 'password' => 'GantiPassword123', 'prodi' => 'Nama Prodi Terdaftar'],
            ],
            'mata-kuliah' => [
                ['kode_mk' => 'CONTOH-MK-001', 'nama_mk' => 'Mata Kuliah Contoh 1', 'sks' => '3', 'semester' => '1', 'prodi' => 'Nama Prodi Terdaftar'],
                ['kode_mk' => 'CONTOH-MK-002', 'nama_mk' => 'Mata Kuliah Contoh 2', 'sks' => '2', 'semester' => '2', 'prodi' => 'Nama Prodi Terdaftar'],
            ],
            'kelas' => [
                ['kode_mk' => 'KODE_MK_TERDAFTAR', 'nidn' => 'NIDN_TERDAFTAR', 'nama_kelas' => 'A', 'kapasitas' => '40', 'tahun' => '', 'semester' => '', 'hari' => '', 'jam_mulai' => '', 'jam_selesai' => '', 'ruangan' => ''],
                ['kode_mk' => 'KODE_MK_TERDAFTAR', 'nidn' => 'NIDN_TERDAFTAR', 'nama_kelas' => 'B', 'kapasitas' => '35', 'tahun' => '', 'semester' => '', 'hari' => '', 'jam_mulai' => '', 'jam_selesai' => '', 'ruangan' => ''],
            ],
            'tahun-akademik' => [
                ['tahun' => '2026/2027', 'semester' => 'ganjil', 'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2026-12-31', 'tanggal_krs_mulai' => '2026-07-20', 'tanggal_krs_selesai' => '2026-08-15'],
                ['tahun' => '2026/2027', 'semester' => 'genap', 'tanggal_mulai' => '2027-02-01', 'tanggal_selesai' => '2027-06-30', 'tanggal_krs_mulai' => '2027-01-20', 'tanggal_krs_selesai' => '2027-02-15'],
            ],
            'ruangan' => [
                ['kode_ruangan' => 'R-CONTOH-01', 'nama_ruangan' => 'Ruangan Contoh 1', 'kapasitas' => '40', 'gedung' => 'Gedung Contoh', 'lantai' => '1', 'fasilitas' => 'Proyektor', 'is_active' => '1', 'fakultas' => 'Nama Fakultas Terdaftar'],
                ['kode_ruangan' => 'R-CONTOH-02', 'nama_ruangan' => 'Ruangan Contoh 2', 'kapasitas' => '30', 'gedung' => 'Gedung Contoh', 'lantai' => '2', 'fasilitas' => 'Papan tulis', 'is_active' => '1', 'fakultas' => 'Nama Fakultas Terdaftar'],
            ],
        };

        return array_map(fn (array $row) => $row + ['penanda_contoh' => 'CONTOH'], $rows);
    }

    public function import(string $type, UploadedFile $file, User $user): int
    {
        if (! isset(self::HEADERS[$type])) {
            abort(404);
        }

        $rows = $this->readRows($file, $type);

        return DB::transaction(function () use ($type, $rows, $user): int {
            $count = match ($type) {
                'fakultas' => $this->importFakultas($rows),
                'prodi' => $this->importProdi($rows, $user),
                'mahasiswa' => $this->importMahasiswa($rows, $user),
                'dosen' => $this->importDosen($rows, $user),
                'mata-kuliah' => $this->importMataKuliah($rows, $user),
                'kelas' => $this->importKelas($rows, $user),
                'tahun-akademik' => $this->importTahunAkademik($rows),
                'ruangan' => $this->importRuangan($rows, $user),
            };

            app(AkademikService::class)->clearAllCache();

            return $count;
        });
    }

    private function readRows(UploadedFile $file, string $type): array
    {
        $path = $file->getRealPath();
        $handle = $path === false ? false : fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('File CSV tidak dapat dibaca.');
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw ValidationException::withMessages(['import_file' => 'File CSV kosong.']);
            }

            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            rewind($handle);
            $headers = fgetcsv($handle, 0, $delimiter, '"', '\\');

            if ($headers === false) {
                throw ValidationException::withMessages(['import_file' => 'Baris judul kolom CSV tidak dapat dibaca.']);
            }

            $headers = array_map(function ($header): string {
                $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);

                return strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $header), '_'));
            }, $headers);

            if (count($headers) !== count(array_unique($headers))) {
                throw ValidationException::withMessages(['import_file' => 'Nama kolom CSV tidak boleh duplikat.']);
            }

            $missingHeaders = array_diff(self::HEADERS[$type], $headers);
            if ($missingHeaders !== []) {
                throw ValidationException::withMessages([
                    'import_file' => 'Kolom wajib belum lengkap: '.implode(', ', $missingHeaders).'. Unduh template untuk melihat format yang benar.',
                ]);
            }

            $rows = [];
            $line = 1;

            while (($values = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                $line++;

                if ($values === [null] || count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                if (count($values) > count($headers)) {
                    $rows[] = ['line' => $line, 'data' => [], 'error' => 'Jumlah kolom melebihi judul kolom.'];

                    continue;
                }

                $data = array_combine($headers, array_pad($values, count($headers), null));
                if (strtoupper(trim((string) ($data['penanda_contoh'] ?? ''))) === 'CONTOH') {
                    continue;
                }

                $rows[] = ['line' => $line, 'data' => $data];
            }

            if ($rows === []) {
                throw ValidationException::withMessages(['import_file' => 'Tidak ada baris data untuk diimpor.']);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function importFakultas(array $rows): int
    {
        $seen = [];
        $valid = $this->validateRows($rows, function (array $data) use (&$seen): array {
            $data['nama'] = trim((string) ($data['nama'] ?? ''));
            $errors = $this->validate($data, ['nama' => ['required', 'string', 'max:255']]);
            $key = strtolower($data['nama']);

            if ($errors === [] && (isset($seen[$key]) || Fakultas::whereRaw('LOWER(nama) = ?', [$key])->exists())) {
                $errors[] = 'Nama fakultas sudah terdaftar.';
            }

            $seen[$key] = true;

            return [$data, $errors];
        });

        foreach ($valid as $data) {
            Fakultas::create(['nama' => $data['nama']]);
        }

        return count($valid);
    }

    private function importProdi(array $rows, User $user): int
    {
        $seen = [];
        $valid = $this->validateRows($rows, function (array $data) use ($user, &$seen): array {
            $data['nama'] = trim((string) ($data['nama'] ?? ''));
            $errors = $this->validate($data, ['nama' => ['required', 'string', 'max:255']]);
            $fakultas = $this->resolveFakultas((string) ($data['fakultas'] ?? ''), $user, $errors);

            if ($fakultas) {
                $key = $fakultas->id.':'.strtolower($data['nama']);
                if ($errors === [] && (isset($seen[$key]) || Prodi::where('fakultas_id', $fakultas->id)->whereRaw('LOWER(nama) = ?', [strtolower($data['nama'])])->exists())) {
                    $errors[] = 'Program studi tersebut sudah terdaftar di fakultas ini.';
                }
                $seen[$key] = true;
                $data['fakultas_id'] = $fakultas->id;
            }

            return [$data, $errors];
        });

        foreach ($valid as $data) {
            Prodi::create(['nama' => $data['nama'], 'fakultas_id' => $data['fakultas_id']]);
        }

        return count($valid);
    }

    private function importMahasiswa(array $rows, User $user): int
    {
        $seenNim = [];
        $seenEmail = [];
        $valid = $this->validateRows($rows, function (array $data) use ($user, &$seenNim, &$seenEmail): array {
            foreach (['nim', 'nama', 'email', 'password', 'prodi', 'angkatan', 'dosen_pa_nidn', 'status'] as $field) {
                $data[$field] = trim((string) ($data[$field] ?? ''));
            }
            $data['status'] = $data['status'] ?: 'aktif';
            $errors = $this->validate($data, [
                'nim' => ['required', 'string', 'max:255', 'unique:mahasiswa,nim'],
                'nama' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
                'angkatan' => ['required', 'digits:4', 'integer', 'min:2000', 'max:'.(date('Y') + 1)],
                'status' => ['required', 'in:aktif,cuti,lulus,do'],
            ]);
            $prodi = $this->resolveProdi($data['prodi'], $user, $errors);

            if ($data['dosen_pa_nidn'] !== '') {
                $dosenQuery = Dosen::where('nidn', $data['dosen_pa_nidn']);
                $this->scopeDosen($dosenQuery, $user);
                if (! $dosenQuery->exists()) {
                    $errors[] = 'NIDN dosen PA tidak ditemukan atau di luar fakultas Anda.';
                }
            }

            if (isset($seenNim[$data['nim']]) || isset($seenEmail[strtolower($data['email'])])) {
                $errors[] = 'NIM atau email duplikat di dalam file.';
            }
            $seenNim[$data['nim']] = true;
            $seenEmail[strtolower($data['email'])] = true;
            $data['prodi_id'] = $prodi?->id;

            return [$data, $errors];
        });

        foreach ($valid as $data) {
            $account = User::create([
                'name' => $data['nama'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'mahasiswa',
            ]);

            Mahasiswa::create([
                'user_id' => $account->id,
                'nim' => $data['nim'],
                'prodi_id' => $data['prodi_id'],
                'dosen_pa_id' => $data['dosen_pa_nidn'] === '' ? null : Dosen::where('nidn', $data['dosen_pa_nidn'])->value('id'),
                'angkatan' => $data['angkatan'],
                'status' => $data['status'],
            ]);
        }

        return count($valid);
    }

    private function importDosen(array $rows, User $user): int
    {
        $seenNidn = [];
        $seenEmail = [];
        $valid = $this->validateRows($rows, function (array $data) use ($user, &$seenNidn, &$seenEmail): array {
            foreach (['nidn', 'nama', 'email', 'password', 'prodi'] as $field) {
                $data[$field] = trim((string) ($data[$field] ?? ''));
            }
            $errors = $this->validate($data, [
                'nidn' => ['required', 'string', 'max:255', 'unique:dosen,nidn'],
                'nama' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
            ]);
            $prodi = $this->resolveProdi($data['prodi'], $user, $errors);

            if (isset($seenNidn[$data['nidn']]) || isset($seenEmail[strtolower($data['email'])])) {
                $errors[] = 'NIDN atau email duplikat di dalam file.';
            }
            $seenNidn[$data['nidn']] = true;
            $seenEmail[strtolower($data['email'])] = true;
            $data['prodi_id'] = $prodi?->id;

            return [$data, $errors];
        });

        foreach ($valid as $data) {
            $account = User::create([
                'name' => $data['nama'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'dosen',
            ]);

            Dosen::create([
                'user_id' => $account->id,
                'nidn' => $data['nidn'],
                'prodi_id' => $data['prodi_id'],
            ]);
        }

        return count($valid);
    }

    private function importMataKuliah(array $rows, User $user): int
    {
        $seen = [];
        $valid = $this->validateRows($rows, function (array $data) use ($user, &$seen): array {
            foreach (['kode_mk', 'nama_mk', 'prodi'] as $field) {
                $data[$field] = trim((string) ($data[$field] ?? ''));
            }
            $errors = $this->validate($data, [
                'kode_mk' => ['required', 'string', 'max:255', 'unique:mata_kuliah,kode_mk'],
                'nama_mk' => ['required', 'string', 'max:255'],
                'sks' => ['required', 'integer', 'min:1'],
                'semester' => ['required', 'integer', 'min:1'],
            ]);
            $prodi = $data['prodi'] === '' ? null : $this->resolveProdi($data['prodi'], $user, $errors);

            if (! $user->isSuperAdmin() && ! $prodi) {
                $errors[] = 'Prodi wajib diisi untuk impor mata kuliah pada fakultas Anda.';
            }
            if (isset($seen[$data['kode_mk']])) {
                $errors[] = 'Kode mata kuliah duplikat di dalam file.';
            }
            $seen[$data['kode_mk']] = true;
            $data['prodi_id'] = $prodi?->id;

            return [$data, $errors];
        });

        foreach ($valid as $data) {
            MataKuliah::create([
                'kode_mk' => $data['kode_mk'],
                'nama_mk' => $data['nama_mk'],
                'sks' => $data['sks'],
                'semester' => $data['semester'],
                'prodi_id' => $data['prodi_id'],
            ]);
        }

        return count($valid);
    }

    private function importKelas(array $rows, User $user): int
    {
        $seen = [];
        $valid = $this->validateRows($rows, function (array $data) use ($user, &$seen): array {
            foreach (['kode_mk', 'nidn', 'nama_kelas', 'tahun', 'semester', 'hari', 'jam_mulai', 'jam_selesai', 'ruangan'] as $field) {
                $data[$field] = trim((string) ($data[$field] ?? ''));
            }
            $errors = $this->validate($data, [
                'kode_mk' => ['required', 'string'],
                'nidn' => ['required', 'string'],
                'nama_kelas' => ['required', 'string', 'max:255'],
                'kapasitas' => ['nullable', 'integer', 'min:1'],
                'tahun' => ['nullable', 'string', 'max:9'],
                'semester' => ['nullable', 'in:ganjil,genap'],
                'hari' => ['nullable', 'in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu'],
                'jam_mulai' => ['nullable', 'date_format:H:i'],
                'jam_selesai' => ['nullable', 'date_format:H:i'],
                'ruangan' => ['nullable', 'string', 'max:50'],
            ]);
            $mataKuliah = MataKuliah::where('kode_mk', $data['kode_mk'])->first();
            if (! $mataKuliah || (! $user->isSuperAdmin() && $mataKuliah->prodi && ! $user->canAccessFakultas((int) $mataKuliah->prodi->fakultas_id))) {
                $errors[] = 'Kode mata kuliah tidak ditemukan atau di luar fakultas Anda.';
            }

            $dosenQuery = Dosen::where('nidn', $data['nidn']);
            $this->scopeDosen($dosenQuery, $user);
            $dosen = $dosenQuery->first();
            if (! $dosen) {
                $errors[] = 'NIDN dosen tidak ditemukan atau di luar fakultas Anda.';
            }

            $tahunAkademik = null;
            if ($data['tahun'] !== '' || $data['semester'] !== '') {
                if ($data['tahun'] === '' || $data['semester'] === '') {
                    $errors[] = 'Tahun dan semester akademik harus diisi bersama.';
                } else {
                    $tahunAkademik = TahunAkademik::where('tahun', $data['tahun'])->where('semester', $data['semester'])->first();
                    if (! $tahunAkademik) {
                        $errors[] = 'Tahun akademik dan semester tidak ditemukan.';
                    }
                }
            } else {
                $tahunAkademik = TahunAkademik::where('is_active', true)->first();
            }

            $scheduleFields = [$data['hari'], $data['jam_mulai'], $data['jam_selesai']];
            if (count(array_filter($scheduleFields, fn ($value) => $value !== '')) !== 0 && count(array_filter($scheduleFields, fn ($value) => $value !== '')) !== 3) {
                $errors[] = 'Hari, jam mulai, dan jam selesai harus diisi bersama.';
            }
            if (isset($seen[$data['kode_mk'].':'.$data['nidn'].':'.$data['nama_kelas'].':'.($tahunAkademik?->id ?? '')])) {
                $errors[] = 'Kelas duplikat di dalam file.';
            }
            $seen[$data['kode_mk'].':'.$data['nidn'].':'.$data['nama_kelas'].':'.($tahunAkademik?->id ?? '')] = true;

            $data['mata_kuliah_id'] = $mataKuliah?->id;
            $data['dosen_id'] = $dosen?->id;
            $data['tahun_akademik_id'] = $tahunAkademik?->id;

            return [$data, $errors];
        });

        foreach ($valid as $data) {
            $duplicate = Kelas::where('mata_kuliah_id', $data['mata_kuliah_id'])
                ->where('dosen_id', $data['dosen_id'])
                ->where('nama_kelas', $data['nama_kelas'])
                ->where('tahun_akademik_id', $data['tahun_akademik_id'])
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages(['import_file' => 'Kelas '.$data['nama_kelas'].' untuk mata kuliah '.$data['kode_mk'].' sudah terdaftar. Tidak ada data dari file yang disimpan.']);
            }
        }

        foreach ($valid as $data) {
            $kelas = Kelas::create([
                'mata_kuliah_id' => $data['mata_kuliah_id'],
                'dosen_id' => $data['dosen_id'],
                'tahun_akademik_id' => $data['tahun_akademik_id'],
                'nama_kelas' => $data['nama_kelas'],
                'kapasitas' => $data['kapasitas'] ?: config('siakad.kelas_kapasitas_default'),
            ]);

            if ($data['hari'] !== '') {
                $kelas->jadwal()->create([
                    'hari' => $data['hari'],
                    'jam_mulai' => $data['jam_mulai'],
                    'jam_selesai' => $data['jam_selesai'],
                    'ruangan' => $data['ruangan'] ?: null,
                ]);
            }
        }

        return count($valid);
    }

    private function importTahunAkademik(array $rows): int
    {
        $seen = [];
        $valid = $this->validateRows($rows, function (array $data) use (&$seen): array {
            foreach (['tahun', 'semester', 'tanggal_mulai', 'tanggal_selesai', 'tanggal_krs_mulai', 'tanggal_krs_selesai'] as $field) {
                $data[$field] = trim((string) ($data[$field] ?? ''));
            }
            $errors = $this->validate($data, [
                'tahun' => ['required', 'string', 'max:9'],
                'semester' => ['required', 'in:ganjil,genap'],
                'tanggal_mulai' => ['nullable', 'date'],
                'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
                'tanggal_krs_mulai' => ['nullable', 'date'],
                'tanggal_krs_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_krs_mulai'],
            ]);
            $key = $data['tahun'].':'.$data['semester'];

            if ($errors === [] && (isset($seen[$key]) || TahunAkademik::where('tahun', $data['tahun'])->where('semester', $data['semester'])->exists())) {
                $errors[] = 'Tahun akademik dan semester tersebut sudah terdaftar.';
            }
            $seen[$key] = true;

            return [$data, $errors];
        });

        foreach ($valid as $data) {
            TahunAkademik::create([
                'tahun' => $data['tahun'],
                'semester' => $data['semester'],
                'is_active' => false,
                'tanggal_mulai' => $data['tanggal_mulai'] ?: null,
                'tanggal_selesai' => $data['tanggal_selesai'] ?: null,
                'tanggal_krs_mulai' => $data['tanggal_krs_mulai'] ?: null,
                'tanggal_krs_selesai' => $data['tanggal_krs_selesai'] ?: null,
            ]);
        }

        return count($valid);
    }

    private function importRuangan(array $rows, User $user): int
    {
        $seen = [];
        $valid = $this->validateRows($rows, function (array $data) use ($user, &$seen): array {
            foreach (['kode_ruangan', 'nama_ruangan', 'gedung', 'fasilitas', 'fakultas'] as $field) {
                $data[$field] = trim((string) ($data[$field] ?? ''));
            }
            $errors = $this->validate($data, [
                'kode_ruangan' => ['required', 'string', 'max:20', 'unique:ruangan,kode_ruangan'],
                'nama_ruangan' => ['required', 'string', 'max:100'],
                'kapasitas' => ['required', 'integer', 'min:1'],
                'lantai' => ['nullable', 'integer', 'min:1'],
                'gedung' => ['nullable', 'string', 'max:50'],
                'fasilitas' => ['nullable', 'string'],
                'is_active' => ['nullable', 'in:0,1'],
            ]);
            $fakultas = $this->resolveFakultas($data['fakultas'], $user, $errors, true);

            if (isset($seen[$data['kode_ruangan']])) {
                $errors[] = 'Kode ruangan duplikat di dalam file.';
            }
            $seen[$data['kode_ruangan']] = true;
            $data['fakultas_id'] = $user->isSuperAdmin() ? $fakultas?->id : $user->fakultas_id;
            $data['is_active'] = $data['is_active'] === '' ? true : $data['is_active'] === '1';

            return [$data, $errors];
        });

        foreach ($valid as $data) {
            Ruangan::create([
                'kode_ruangan' => $data['kode_ruangan'],
                'nama_ruangan' => $data['nama_ruangan'],
                'kapasitas' => $data['kapasitas'],
                'gedung' => $data['gedung'] ?: null,
                'lantai' => $data['lantai'] ?: null,
                'fasilitas' => $data['fasilitas'] ?: null,
                'is_active' => $data['is_active'],
                'fakultas_id' => $data['fakultas_id'],
            ]);
        }

        return count($valid);
    }

    private function validateRows(array $rows, callable $callback): array
    {
        $valid = [];
        $errors = [];

        foreach ($rows as $row) {
            if (isset($row['error'])) {
                $errors[] = 'Baris '.$row['line'].': '.$row['error'];

                continue;
            }

            [$data, $rowErrors] = $callback($row['data']);
            if ($rowErrors !== []) {
                foreach ($rowErrors as $error) {
                    $errors[] = 'Baris '.$row['line'].': '.$error;
                }

                continue;
            }

            $valid[] = $data;
        }

        if ($errors !== []) {
            $reportedErrors = array_slice($errors, 0, 50);
            if (count($errors) > count($reportedErrors)) {
                $reportedErrors[] = (count($errors) - count($reportedErrors)).' kesalahan lainnya tidak ditampilkan.';
            }

            throw ValidationException::withMessages(['import_file' => $reportedErrors]);
        }

        return $valid;
    }

    private function validate(array $data, array $rules): array
    {
        $data = array_map(fn ($value) => $value === '' ? null : $value, $data);
        $validator = Validator::make($data, $rules);

        if (! $validator->fails()) {
            return [];
        }

        $errors = [];
        foreach ($validator->failed() as $field => $failedRules) {
            foreach ($failedRules as $rule => $parameters) {
                $errors[] = $this->validationMessage($field, $rule, $parameters, $data, $rules);
            }
        }

        return $errors;
    }

    private function validationMessage(string $field, string $rule, array $parameters, array $data, array $rules): string
    {
        $label = self::FIELD_LABELS[$field] ?? $field;
        $value = $data[$field] ?? null;
        $fieldRules = $rules[$field] ?? [];
        $isNumeric = in_array('numeric', $fieldRules, true) || in_array('integer', $fieldRules, true);

        $reason = match ($rule) {
            'Required' => 'wajib diisi.',
            'Email' => 'harus menggunakan format email yang valid.',
            'Unique' => 'sudah digunakan oleh data lain.',
            'Integer' => 'harus berupa angka bulat.',
            'Digits' => 'harus terdiri dari '.($parameters[0] ?? 'jumlah').' digit.',
            'Min' => $isNumeric
                ? 'minimal bernilai '.($parameters[0] ?? '').'.'
                : 'minimal berisi '.($parameters[0] ?? '').' karakter.',
            'Max' => $isNumeric
                ? 'maksimal bernilai '.($parameters[0] ?? '').'.'
                : 'maksimal berisi '.($parameters[0] ?? '').' karakter.',
            'In' => 'harus salah satu dari: '.implode(', ', $parameters).'.',
            'Date' => 'harus menggunakan tanggal yang valid (YYYY-MM-DD).',
            'AfterOrEqual' => 'tidak boleh sebelum kolom '.(self::FIELD_LABELS[$parameters[0] ?? ''] ?? ($parameters[0] ?? 'tanggal acuan')).'.',
            'DateFormat' => 'harus menggunakan format '.($parameters[0] ?? 'tanggal/jam').' yang benar.',
            'String' => 'harus berupa teks.',
            'Exists' => 'tidak ditemukan pada data master.',
            default => 'tidak valid.',
        };

        return "Kolom {$label}: {$reason}";
    }

    private function resolveFakultas(string $name, User $user, array &$errors, bool $optional = false): ?Fakultas
    {
        if (! $user->isSuperAdmin()) {
            if (! $user->fakultas_id) {
                $errors[] = 'Akun Anda belum terhubung ke fakultas.';

                return null;
            }

            $fakultas = Fakultas::find($user->fakultas_id);
            if (! $fakultas) {
                $errors[] = 'Fakultas akun Anda tidak ditemukan.';

                return null;
            }

            if ($name !== '' && strcasecmp($fakultas->nama, $name) !== 0) {
                $errors[] = 'Fakultas pada file tidak sesuai dengan cakupan akun Anda.';
            }

            return $fakultas;
        }

        if ($name === '' && $optional) {
            return null;
        }

        if ($name === '') {
            $errors[] = 'Nama fakultas wajib diisi.';

            return null;
        }

        $matches = Fakultas::whereRaw('LOWER(nama) = ?', [strtolower($name)])->get();
        if ($matches->count() !== 1) {
            $errors[] = $matches->isEmpty() ? 'Fakultas tidak ditemukan.' : 'Nama fakultas tidak unik; gunakan nama yang berbeda.';

            return null;
        }

        return $matches->first();
    }

    private function resolveProdi(string $name, User $user, array &$errors): ?Prodi
    {
        if ($name === '') {
            $errors[] = 'Nama prodi wajib diisi.';

            return null;
        }

        $query = Prodi::whereRaw('LOWER(nama) = ?', [strtolower($name)]);
        if (! $user->isSuperAdmin()) {
            $query->where('fakultas_id', $user->fakultas_id);
        }
        $matches = $query->get();

        if ($matches->count() !== 1) {
            $errors[] = $matches->isEmpty() ? 'Prodi tidak ditemukan atau di luar fakultas Anda.' : 'Nama prodi tidak unik; gunakan nama yang berbeda.';

            return null;
        }

        return $matches->first();
    }

    private function scopeDosen(Builder $query, User $user): void
    {
        if (! $user->isSuperAdmin()) {
            $query->whereHas('prodi', fn ($prodiQuery) => $prodiQuery->where('fakultas_id', $user->fakultas_id));
        }
    }
}
