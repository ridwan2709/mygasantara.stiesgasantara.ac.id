<?php

namespace Database\Seeders;

use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\Honorarium;
use App\Models\InsentifManual;
use App\Models\JadwalKuliah;
use App\Models\KehadiranMengajar;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\KrsDetail;
use App\Models\Mahasiswa;
use App\Models\MataKuliah;
use App\Models\Pertemuan;
use App\Models\Presensi;
use App\Models\Prodi;
use App\Models\Ruangan;
use App\Models\TahunAkademik;
use App\Models\TarifHonorarium;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CurrentAcademicDemoSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'Demo123!';

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call(RolePermissionSeeder::class);

            $fakultas = Fakultas::firstOrCreate([
                'nama' => 'Fakultas Demo SIAKAD',
            ]);
            $admin = $this->demoUser(
                'demo.admin@siakad.test',
                'Admin Demo Fakultas',
                'admin_fakultas',
                $fakultas->id,
            );
            $superadmin = $this->demoUser(
                'demo.superadmin@siakad.test',
                'Superadmin Demo',
                'superadmin',
            );
            $prodi = Prodi::firstOrCreate([
                'fakultas_id' => $fakultas->id,
                'nama' => 'Teknik Informatika Demo',
            ]);

            [$term, $termStart, $termEnd] = $this->activeTerm();
            $rooms = $this->seedRooms($fakultas->id);
            $lecturers = $this->seedLecturers($prodi);
            $students = $this->seedStudents($prodi, $lecturers);
            $courses = $this->seedCourses($prodi);
            $classes = $this->seedClasses($term, $courses, $lecturers, $rooms);
            $this->seedEnrollments($term, $classes, $students);
            $this->seedMeetingsAndAttendance($classes, $students, $termStart);

            $monthStart = today()->startOfMonth();
            $this->ensureCurrentTariff($monthStart, $admin->id);
            $this->seedIncentives($lecturers, $admin, today());
            $this->seedHonorarium($lecturers, today());
            $this->seedPreviousPaidHonorarium($lecturers[0], today());

            $this->command?->newLine();
            $this->command?->info('Data demo akademik aktif berhasil dibuat atau diperbarui.');
            $this->command?->table(
                ['Jenis', 'Jumlah'],
                [
                    ['Fakultas demo', 1],
                    ['Program studi demo', 1],
                    ['Periode akademik aktif', $term->tahun.' '.$term->semester],
                    ['Dosen demo', count($lecturers)],
                    ['Mahasiswa demo', count($students)],
                    ['Mata kuliah dan kelas', count($classes)],
                    ['Jadwal kuliah', count($classes)],
                ],
            );
            $this->command?->newLine();
            $this->command?->info('Password seluruh akun demo: '.self::DEMO_PASSWORD);
            $this->command?->table(
                ['Peran', 'Email'],
                [
                    ['Superadmin', $superadmin->email],
                    ['Admin fakultas', $admin->email],
                    ...array_map(fn (Dosen $dosen): array => ['Dosen', $dosen->user->email], $lecturers),
                    ...array_map(fn (Mahasiswa $mahasiswa): array => ['Mahasiswa', $mahasiswa->user->email], $students),
                ],
            );
            $this->command?->info('Periode aktif: '.$termStart->toDateString().' s.d. '.$termEnd->toDateString());
        });
    }

    private function demoUser(string $email, string $name, string $role, ?int $fakultasId = null): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(self::DEMO_PASSWORD),
                'email_verified_at' => now(),
                'role' => $role,
                'fakultas_id' => $fakultasId,
            ],
        );
    }

    /**
     * @return array{TahunAkademik, Carbon, Carbon}
     */
    private function activeTerm(): array
    {
        $today = today();

        if ($today->month >= 9 || $today->month === 1) {
            $startYear = $today->month === 1 ? $today->year - 1 : $today->year;
            $start = Carbon::create($startYear, 9, 1)->startOfDay();
            $end = Carbon::create($startYear + 1, 2, 1)->subDay()->startOfDay();
            $semester = 'Ganjil';
        } else {
            $startYear = $today->month === 2 ? $today->year : $today->year - 1;
            $start = Carbon::create($startYear, 2, 1)->startOfDay();
            $end = Carbon::create($startYear, 9, 1)->subDay()->startOfDay();
            $semester = 'Genap';
        }

        $term = TahunAkademik::updateOrCreate(
            [
                'tahun' => $startYear.'/'.($startYear + 1),
                'semester' => $semester,
            ],
            [
                'is_active' => true,
                'tanggal_mulai' => $start->toDateString(),
                'tanggal_selesai' => $end->toDateString(),
            ],
        );

        TahunAkademik::query()
            ->where('id', '!=', $term->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return [$term, $start, $end];
    }

    /**
     * @return array<int, Ruangan>
     */
    private function seedRooms(int $fakultasId): array
    {
        $rooms = [];

        foreach (range(1, 6) as $number) {
            $rooms[] = Ruangan::updateOrCreate(
                ['kode_ruangan' => sprintf('DEMO-%02d', $number)],
                [
                    'nama_ruangan' => 'Ruang Demo '.$number,
                    'kapasitas' => 40,
                    'gedung' => 'Gedung Demo',
                    'lantai' => 1 + intdiv($number - 1, 3),
                    'fasilitas' => 'Proyektor, papan tulis, Wi-Fi',
                    'is_active' => true,
                    'fakultas_id' => $fakultasId,
                ],
            );
        }

        return $rooms;
    }

    /**
     * @return array<int, Dosen>
     */
    private function seedLecturers(Prodi $prodi): array
    {
        $lecturerData = [
            ['9900000001', 'Dr. Rina Amalia, M.Kom.', 'demo.dosen1@siakad.test'],
            ['9900000002', 'Ahmad Pratama, M.Kom.', 'demo.dosen2@siakad.test'],
            ['9900000003', 'Siti Rahmawati, M.T.', 'demo.dosen3@siakad.test'],
            ['9900000004', 'Budi Santoso, M.Kom.', 'demo.dosen4@siakad.test'],
        ];

        return array_map(function (array $data) use ($prodi): Dosen {
            [$nidn, $name, $email] = $data;
            $user = $this->demoUser($email, $name, 'dosen');

            return Dosen::updateOrCreate(
                ['nidn' => $nidn],
                ['user_id' => $user->id, 'prodi_id' => $prodi->id],
            );
        }, $lecturerData);
    }

    /**
     * @param  array<int, Dosen>  $lecturers
     * @return array<int, Mahasiswa>
     */
    private function seedStudents(Prodi $prodi, array $lecturers): array
    {
        $names = [
            'Andi Wijaya', 'Dewi Lestari', 'Fajar Nugraha', 'Intan Permata',
            'Rizky Ramadhan', 'Nadia Putri', 'Dimas Saputra', 'Putri Maharani',
            'Yoga Prasetyo', 'Citra Anggraini', 'Rafi Hidayat', 'Nisa Aulia',
        ];

        return array_map(function (string $name, int $index) use ($prodi, $lecturers): Mahasiswa {
            $number = $index + 1;
            $user = $this->demoUser(
                sprintf('demo.mahasiswa%02d@siakad.test', $number),
                $name,
                'mahasiswa',
            );

            return Mahasiswa::updateOrCreate(
                ['nim' => sprintf('DEMO2026%04d', $number)],
                [
                    'user_id' => $user->id,
                    'prodi_id' => $prodi->id,
                    'dosen_pa_id' => $lecturers[($index) % count($lecturers)]->id,
                    'angkatan' => (string) today()->year,
                    'status' => 'aktif',
                ],
            );
        }, $names, array_keys($names));
    }

    /**
     * @return array<int, MataKuliah>
     */
    private function seedCourses(Prodi $prodi): array
    {
        $courseData = [
            ['DEMO101', 'Algoritma dan Pemrograman', 4],
            ['DEMO102', 'Basis Data', 3],
            ['DEMO103', 'Matematika Diskrit', 3],
            ['DEMO104', 'Pemrograman Web', 4],
            ['DEMO105', 'Sistem Informasi Manajemen', 3],
            ['DEMO106', 'Jaringan Komputer', 3],
        ];

        return array_map(function (array $data) use ($prodi): MataKuliah {
            [$code, $name, $credits] = $data;

            return MataKuliah::updateOrCreate(
                ['kode_mk' => $code],
                [
                    'nama_mk' => $name,
                    'sks' => $credits,
                    'semester' => 1,
                    'prodi_id' => $prodi->id,
                ],
            );
        }, $courseData);
    }

    /**
     * @param  array<int, MataKuliah>  $courses
     * @param  array<int, Dosen>  $lecturers
     * @param  array<int, Ruangan>  $rooms
     * @return array<int, Kelas>
     */
    private function seedClasses(TahunAkademik $term, array $courses, array $lecturers, array $rooms): array
    {
        $scheduleData = [
            ['Senin', '08:00', '09:30'],
            ['Selasa', '09:45', '11:15'],
            ['Rabu', '13:00', '14:30'],
            ['Kamis', '15:00', '16:30'],
            ['Jumat', '08:00', '09:30'],
            ['Sabtu', '10:00', '11:30'],
        ];

        $classes = [];
        foreach ($courses as $index => $course) {
            $class = Kelas::updateOrCreate(
                [
                    'mata_kuliah_id' => $course->id,
                    'dosen_id' => $lecturers[$index % count($lecturers)]->id,
                    'tahun_akademik_id' => $term->id,
                    'nama_kelas' => 'A',
                ],
                ['kapasitas' => 40, 'is_closed' => false],
            );

            [$day, $start, $end] = $scheduleData[$index];
            JadwalKuliah::updateOrCreate(
                ['kelas_id' => $class->id],
                [
                    'hari' => $day,
                    'jam_mulai' => $start,
                    'jam_selesai' => $end,
                    'ruangan' => $rooms[$index]->nama_ruangan,
                ],
            );

            $classes[] = $class;
        }

        return $classes;
    }

    /**
     * @param  array<int, Kelas>  $classes
     * @param  array<int, Mahasiswa>  $students
     */
    private function seedEnrollments(TahunAkademik $term, array $classes, array $students): void
    {
        foreach ($students as $studentIndex => $student) {
            $status = $studentIndex < 10 ? 'approved' : 'pending';
            $krs = Krs::updateOrCreate(
                ['mahasiswa_id' => $student->id, 'tahun_akademik_id' => $term->id],
                ['status' => $status],
            );

            $classesForStudent = $status === 'approved' ? $classes : array_slice($classes, 0, 2);
            foreach ($classesForStudent as $class) {
                KrsDetail::updateOrCreate(
                    ['krs_id' => $krs->id, 'kelas_id' => $class->id],
                    [],
                );
            }
        }
    }

    /**
     * @param  array<int, Kelas>  $classes
     * @param  array<int, Mahasiswa>  $students
     */
    private function seedMeetingsAndAttendance(array $classes, array $students, Carbon $termStart): void
    {
        $today = today();
        $meetingStart = $today->copy()->subWeeks(6)->startOfDay();
        if ($meetingStart->lt($termStart)) {
            $meetingStart = $termStart->copy();
        }

        foreach ($classes as $classIndex => $class) {
            $schedule = JadwalKuliah::where('kelas_id', $class->id)->firstOrFail();
            $classStudents = KrsDetail::with('krs.mahasiswa')
                ->where('kelas_id', $class->id)
                ->whereHas('krs', fn ($query) => $query->where('status', 'approved'))
                ->get()
                ->map(fn (KrsDetail $detail): Mahasiswa => $detail->krs->mahasiswa);
            $dates = [];
            foreach (CarbonPeriod::create($meetingStart, $today) as $date) {
                if ($date->isoWeekday() === $this->weekdayNumber($schedule->hari)) {
                    $dates[] = $date->copy();
                }
            }
            $dates = array_slice($dates, -6);

            foreach ($dates as $meetingIndex => $date) {
                $meeting = Pertemuan::updateOrCreate(
                    [
                        'jadwal_kuliah_id' => $schedule->id,
                        'pertemuan_ke' => $meetingIndex + 1,
                    ],
                    [
                        'tanggal' => $date->toDateString(),
                        'materi' => 'Materi pertemuan '.($meetingIndex + 1).' — '.$class->mataKuliah->nama_mk,
                        'status' => Pertemuan::STATUS_TERLAKSANA,
                    ],
                );

                foreach ($classStudents as $studentIndex => $student) {
                    $attendanceStatus = match (($studentIndex + $meetingIndex) % 9) {
                        0 => Presensi::STATUS_ALPA,
                        4 => Presensi::STATUS_IZIN,
                        7 => Presensi::STATUS_SAKIT,
                        default => Presensi::STATUS_HADIR,
                    };

                    Presensi::updateOrCreate(
                        ['pertemuan_id' => $meeting->id, 'mahasiswa_id' => $student->id],
                        [
                            'status' => $attendanceStatus,
                            'waktu_presensi' => $attendanceStatus === Presensi::STATUS_HADIR ? $schedule->jam_mulai : null,
                            'keterangan' => $attendanceStatus === Presensi::STATUS_HADIR ? null : 'Data contoh untuk pengujian presensi.',
                        ],
                    );
                }

                if ($date->month === $today->month && $date->year === $today->year) {
                    $this->seedTeachingAttendance($class, $meeting, $schedule, $classIndex, $dates);
                }
            }
        }
    }

    /**
     * @param  array<int, Carbon>  $classDates
     */
    private function seedTeachingAttendance(
        Kelas $class,
        Pertemuan $meeting,
        JadwalKuliah $schedule,
        int $classIndex,
        array $classDates,
    ): void {
        $currentMonthDates = array_values(array_filter(
            $classDates,
            fn (Carbon $date): bool => $date->month === today()->month && $date->year === today()->year,
        ));
        $dateIsLatestInMonth = $meeting->tanggal->isSameDay(end($currentMonthDates));
        $verificationStatus = 'approved';
        if ($dateIsLatestInMonth && count($currentMonthDates) > 1 && $classIndex === 0) {
            $verificationStatus = 'rejected';
        } elseif ($dateIsLatestInMonth && count($currentMonthDates) > 1 && $classIndex === 1) {
            $verificationStatus = 'pending';
        }

        KehadiranMengajar::updateOrCreate(
            [
                'kelas_id' => $class->id,
                'tanggal' => $meeting->tanggal->toDateString(),
                'jam_mulai' => $schedule->jam_mulai,
            ],
            [
                'dosen_id' => $class->dosen_id,
                'mata_kuliah_id' => $class->mata_kuliah_id,
                'jam_selesai' => $schedule->jam_selesai,
                'status_hadir' => 'hadir',
                'status_verifikasi' => $verificationStatus,
                'diverifikasi_oleh' => $verificationStatus === 'pending' ? null : User::where('email', 'demo.admin@siakad.test')->value('id'),
                'diverifikasi_pada' => $verificationStatus === 'pending' ? null : $meeting->tanggal->copy()->setTime(17, 0),
                'keterangan' => 'Data demo untuk periode berjalan.',
            ],
        );
    }

    private function ensureCurrentTariff(Carbon $monthStart, int $adminId): void
    {
        $tarifAdaPadaAwalPeriode = TarifHonorarium::query()
            ->whereDate('berlaku_mulai', '<=', $monthStart->toDateString())
            ->where(fn ($query) => $query->whereNull('berlaku_sampai')
                ->orWhereDate('berlaku_sampai', '>=', $monthStart->toDateString()))
            ->exists();

        if (! $tarifAdaPadaAwalPeriode) {
            TarifHonorarium::firstOrCreate(
                ['basis_tarif' => 'per_sks', 'berlaku_mulai' => $monthStart->toDateString()],
                ['nominal' => 35000, 'dibuat_oleh' => $adminId],
            );
        }
    }

    /**
     * @param  array<int, Dosen>  $lecturers
     */
    private function seedIncentives(array $lecturers, User $admin, Carbon $today): void
    {
        foreach ($lecturers as $index => $lecturer) {
            InsentifManual::firstOrCreate(
                [
                    'dosen_id' => $lecturer->id,
                    'jenis_insentif' => 'Tunjangan kegiatan demo',
                    'tanggal' => $today->copy()->startOfMonth()->addDays(2 + $index)->toDateString(),
                ],
                [
                    'nominal' => 100000 + ($index * 25000),
                    'keterangan' => 'Data demo honorarium untuk periode berjalan.',
                    'input_by' => $admin->id,
                ],
            );
        }
    }

    /**
     * @param  array<int, Dosen>  $lecturers
     */
    private function seedHonorarium(array $lecturers, Carbon $today): void
    {
        foreach ($lecturers as $lecturer) {
            $existing = Honorarium::query()
                ->where('dosen_id', $lecturer->id)
                ->where('bulan', $today->month)
                ->where('tahun', $today->year)
                ->first();
            if ($existing?->status_pembayaran === Honorarium::STATUS_PAID) {
                continue;
            }

            $attendance = KehadiranMengajar::with('mataKuliah')
                ->where('dosen_id', $lecturer->id)
                ->whereYear('tanggal', $today->year)
                ->whereMonth('tanggal', $today->month)
                ->where('status_hadir', 'hadir')
                ->where('status_verifikasi', KehadiranMengajar::STATUS_APPROVED)
                ->get();
            $teachingPay = 0.0;
            foreach ($attendance as $meeting) {
                $tariff = TarifHonorarium::query()
                    ->whereDate('berlaku_mulai', '<=', $meeting->tanggal->toDateString())
                    ->where(fn ($query) => $query->whereNull('berlaku_sampai')
                        ->orWhereDate('berlaku_sampai', '>=', $meeting->tanggal->toDateString()))
                    ->latest('berlaku_mulai')
                    ->firstOrFail();
                $teachingPay += $tariff->basis_tarif === 'per_sks'
                    ? (float) $tariff->nominal * (int) $meeting->mataKuliah->sks
                    : (float) $tariff->nominal;
            }
            $manualIncentives = (float) InsentifManual::query()
                ->where('dosen_id', $lecturer->id)
                ->whereYear('tanggal', $today->year)
                ->whereMonth('tanggal', $today->month)
                ->sum('nominal');

            Honorarium::updateOrCreate(
                [
                    'dosen_id' => $lecturer->id,
                    'bulan' => $today->month,
                    'tahun' => $today->year,
                ],
                [
                    'total_kehadiran' => $attendance->count(),
                    'total_honor_mengajar' => round($teachingPay, 2),
                    'total_insentif_manual' => round($manualIncentives, 2),
                    'total_penerimaan' => round($teachingPay + $manualIncentives, 2),
                    'status_pembayaran' => Honorarium::STATUS_DRAFT,
                    'tanggal_bayar' => null,
                    'direkap_pada' => now(),
                ],
            );
        }
    }

    private function seedPreviousPaidHonorarium(Dosen $lecturer, Carbon $today): void
    {
        $previousMonth = $today->copy()->subMonthNoOverflow();
        Honorarium::firstOrCreate(
            [
                'dosen_id' => $lecturer->id,
                'bulan' => $previousMonth->month,
                'tahun' => $previousMonth->year,
            ],
            [
                'total_kehadiran' => 8,
                'total_honor_mengajar' => 1120000,
                'total_insentif_manual' => 150000,
                'total_penerimaan' => 1270000,
                'status_pembayaran' => Honorarium::STATUS_PAID,
                'tanggal_bayar' => $previousMonth->copy()->endOfMonth()->toDateString(),
                'direkap_pada' => $previousMonth->copy()->endOfMonth(),
            ],
        );
    }

    private function weekdayNumber(string $day): int
    {
        return match ($day) {
            'Senin' => 1,
            'Selasa' => 2,
            'Rabu' => 3,
            'Kamis' => 4,
            'Jumat' => 5,
            'Sabtu' => 6,
        };
    }
}
