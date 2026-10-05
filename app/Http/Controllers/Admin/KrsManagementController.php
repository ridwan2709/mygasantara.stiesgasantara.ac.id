<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\KrsDetail;
use App\Models\Mahasiswa;
use App\Models\MataKuliah;
use App\Models\Prodi;
use App\Models\TahunAkademik;
use App\Services\KrsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KrsManagementController extends Controller
{
    public function __construct(private KrsService $krsService)
    {
    }

    public function create(Request $request): View
    {
        $tahunAkademik = TahunAkademik::query()->where('is_active', true)->first();
        $prodiList = $this->prodiQuery($request)->orderBy('nama')->get();
        $prodi = null;
        $angkatanList = collect();
        $semesterList = collect();
        $mahasiswaCount = 0;
        $availableKelas = collect();
        $maxSks = config('siakad.maks_sks.default', 24);

        if ($request->filled('prodi_id')) {
            $prodi = $this->prodiQuery($request)->findOrFail($request->integer('prodi_id'));
            $angkatanList = Mahasiswa::query()
                ->where('prodi_id', $prodi->id)
                ->where('status', 'aktif')
                ->distinct()
                ->orderBy('angkatan')
                ->pluck('angkatan');

            $semesterList = MataKuliah::query()
                ->where('prodi_id', $prodi->id)
                ->whereHas('kelas', fn (Builder $query) => $this->activeSemesterClasses($query, $tahunAkademik))
                ->distinct()
                ->orderBy('semester')
                ->pluck('semester');
        }

        if ($prodi && $request->filled('angkatan')) {
            $angkatan = (string) $request->input('angkatan');
            abort_unless($angkatanList->contains($angkatan), 404);

            $mahasiswaList = $this->cohortQuery($prodi, $angkatan)->get();
            $mahasiswaCount = $mahasiswaList->count();

            if ($mahasiswaCount > 0) {
                $maxSks = $mahasiswaList
                    ->map(fn (Mahasiswa $mahasiswa) => $this->krsService->getMaxSksForMahasiswa($mahasiswa))
                    ->min();
            }

            if ($request->filled('semester') && $tahunAkademik) {
                $semester = $request->integer('semester');
                abort_unless($semesterList->contains($semester), 404);

                $krsIds = Krs::query()
                    ->where('tahun_akademik_id', $tahunAkademik->id)
                    ->whereIn('mahasiswa_id', $mahasiswaList->modelKeys())
                    ->pluck('id');

                $cohortEnrollments = KrsDetail::query()
                    ->whereIn('krs_id', $krsIds)
                    ->selectRaw('kelas_id, COUNT(*) as enrollment_count')
                    ->groupBy('kelas_id')
                    ->pluck('enrollment_count', 'kelas_id');

                $availableKelas = Kelas::query()
                    ->with(['mataKuliah', 'dosen.user'])
                    ->withCount('krsDetail')
                    ->where(function (Builder $query) use ($tahunAkademik) {
                        $this->activeSemesterClasses($query, $tahunAkademik);
                    })
                    ->whereHas('mataKuliah', fn (Builder $query) => $query
                        ->where('prodi_id', $prodi->id)
                        ->where('semester', $semester))
                    ->orderBy('mata_kuliah_id')
                    ->orderBy('nama_kelas')
                    ->get()
                    ->filter(function (Kelas $kelas) use ($cohortEnrollments, $mahasiswaCount): bool {
                        $outsideEnrollment = $kelas->krs_detail_count - $cohortEnrollments->get($kelas->id, 0);
                        $remainingSeats = $kelas->kapasitas - $outsideEnrollment;
                        $canFitCohort = $remainingSeats >= $mahasiswaCount;
                        $kelas->setAttribute('available_for_cohort', !$kelas->is_closed && $canFitCohort);
                        $kelas->setAttribute('cohort_remaining_seats', max(0, $remainingSeats));

                        return true;
                    })
                    ->groupBy('mata_kuliah_id');
            }
        }

        return view('admin.krs.create', compact(
            'tahunAkademik',
            'prodiList',
            'prodi',
            'angkatanList',
            'semesterList',
            'mahasiswaCount',
            'availableKelas',
            'maxSks',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prodi_id' => ['required', 'integer', 'exists:prodi,id'],
            'angkatan' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'integer', 'min:1'],
            'mata_kuliah_ids' => ['required', 'array', 'min:1'],
            'mata_kuliah_ids.*' => ['required', 'integer', 'distinct', 'exists:mata_kuliah,id'],
            'kelas_per_mata_kuliah' => ['required', 'array'],
            'kelas_per_mata_kuliah.*' => ['required', 'integer', 'distinct', 'exists:kelas,id'],
        ]);

        $tahunAkademik = TahunAkademik::query()->where('is_active', true)->first();

        if (!$tahunAkademik) {
            return redirect()->route('admin.krs.create')
                ->with('error', 'Tidak ada tahun akademik aktif.');
        }

        $prodi = $this->prodiQuery($request)->findOrFail($validated['prodi_id']);
        $angkatanList = Mahasiswa::query()
            ->where('prodi_id', $prodi->id)
            ->where('status', 'aktif')
            ->distinct()
            ->pluck('angkatan');

        if (!$angkatanList->contains((string) $validated['angkatan'])) {
            throw ValidationException::withMessages([
                'angkatan' => 'Angkatan tidak ditemukan pada program studi yang dipilih.',
            ]);
        }

        $semesterExists = MataKuliah::query()
            ->where('prodi_id', $prodi->id)
            ->where('semester', $validated['semester'])
            ->whereHas('kelas', fn (Builder $query) => $this->activeSemesterClasses($query, $tahunAkademik))
            ->exists();

        if (!$semesterExists) {
            throw ValidationException::withMessages([
                'semester' => 'Tidak ada mata kuliah dan kelas aktif untuk semester tersebut.',
            ]);
        }

        $mahasiswaList = $this->cohortQuery($prodi, (string) $validated['angkatan'])->get();

        if ($mahasiswaList->isEmpty()) {
            throw ValidationException::withMessages([
                'angkatan' => 'Tidak ada mahasiswa aktif pada kelompok tersebut.',
            ]);
        }

        $courseIds = collect($validated['mata_kuliah_ids'])->map(fn ($id) => (int) $id);
        $classSelection = collect($validated['kelas_per_mata_kuliah'])
            ->mapWithKeys(fn ($kelasId, $courseId) => [(int) $courseId => (int) $kelasId]);

        if ($classSelection->keys()->sort()->values()->all() !== $courseIds->sort()->values()->all()) {
            throw ValidationException::withMessages([
                'kelas_per_mata_kuliah' => 'Pilih satu kelas untuk setiap mata kuliah yang dicentang.',
            ]);
        }

        $kelasList = Kelas::query()
            ->with('mataKuliah')
            ->withCount('krsDetail')
            ->whereIn('id', $classSelection->values())
            ->where('is_closed', false)
            ->where(function (Builder $query) use ($tahunAkademik) {
                $this->activeSemesterClasses($query, $tahunAkademik);
            })
            ->whereHas('mataKuliah', fn (Builder $query) => $query
                ->where('prodi_id', $prodi->id)
                ->where('semester', $validated['semester'])
                ->whereIn('id', $courseIds))
            ->get();

        if ($kelasList->count() !== $courseIds->count()
            || $kelasList->pluck('mata_kuliah_id')->sort()->values()->all() !== $courseIds->sort()->values()->all()) {
            throw ValidationException::withMessages([
                'kelas_per_mata_kuliah' => 'Kelas harus sesuai dengan mata kuliah, program studi, semester, dan tahun akademik aktif.',
            ]);
        }

        $totalSks = $kelasList->sum(fn (Kelas $kelas) => $kelas->mataKuliah->sks);
        $maxGroupSks = $mahasiswaList
            ->map(fn (Mahasiswa $mahasiswa) => $this->krsService->getMaxSksForMahasiswa($mahasiswa))
            ->min();

        if ($totalSks > $maxGroupSks) {
            throw ValidationException::withMessages([
                'mata_kuliah_ids' => "Total SKS kelompok melebihi batas mahasiswa dengan batas terendah, yaitu {$maxGroupSks} SKS.",
            ]);
        }

        DB::transaction(function () use ($mahasiswaList, $tahunAkademik, $kelasList): void {
            $lockedClasses = Kelas::query()
                ->with('mataKuliah')
                ->withCount('krsDetail')
                ->whereIn('id', $kelasList->modelKeys())
                ->lockForUpdate()
                ->get();

            if ($lockedClasses->count() !== $kelasList->count()) {
                throw ValidationException::withMessages([
                    'kelas_per_mata_kuliah' => 'Salah satu kelas yang dipilih sudah tidak tersedia.',
                ]);
            }

            $currentKrsIds = Krs::query()
                ->where('tahun_akademik_id', $tahunAkademik->id)
                ->whereIn('mahasiswa_id', $mahasiswaList->modelKeys())
                ->pluck('id');
            $currentCohortEnrollments = KrsDetail::query()
                ->whereIn('krs_id', $currentKrsIds)
                ->selectRaw('kelas_id, COUNT(*) as enrollment_count')
                ->groupBy('kelas_id')
                ->pluck('enrollment_count', 'kelas_id');

            foreach ($lockedClasses as $kelas) {
                $outsideEnrollment = $kelas->krs_detail_count - $currentCohortEnrollments->get($kelas->id, 0);

                if ($kelas->is_closed || $outsideEnrollment + $mahasiswaList->count() > $kelas->kapasitas) {
                    throw ValidationException::withMessages([
                        'kelas_per_mata_kuliah' => "Kelas {$kelas->nama_kelas} untuk {$kelas->mataKuliah->nama_mk} tidak cukup menampung seluruh kelompok mahasiswa.",
                    ]);
                }
            }

            foreach ($mahasiswaList as $mahasiswa) {
                $krs = Krs::query()->firstOrCreate(
                    [
                        'mahasiswa_id' => $mahasiswa->id,
                        'tahun_akademik_id' => $tahunAkademik->id,
                    ],
                    ['status' => 'draft'],
                );

                $krs->update(['status' => 'draft', 'catatan' => null]);
                $krs->krsDetail()->delete();
                $krs->krsDetail()->createMany(
                    $lockedClasses->map(fn (Kelas $kelas) => ['kelas_id' => $kelas->id])->all(),
                );
                $krs->update(['status' => 'approved']);
            }
        });

        return redirect()->route('admin.krs-approval.index', ['status' => 'approved'])
            ->with('success', "KRS berhasil dibuat dan disetujui untuk {$mahasiswaList->count()} mahasiswa.");
    }

    private function prodiQuery(Request $request): Builder
    {
        $query = Prodi::query()->with('fakultas');

        if (!$request->user()->isSuperAdmin()) {
            if (!$request->user()->fakultas_id) {
                return $query->whereRaw('1 = 0');
            }

            $query->where('fakultas_id', $request->user()->fakultas_id);
        }

        return $query;
    }

    private function cohortQuery(Prodi $prodi, string $angkatan): Builder
    {
        return Mahasiswa::query()
            ->with(['user', 'prodi'])
            ->where('prodi_id', $prodi->id)
            ->where('angkatan', $angkatan)
            ->where('status', 'aktif')
            ->orderBy('nim');
    }

    private function activeSemesterClasses(Builder $query, ?TahunAkademik $tahunAkademik): Builder
    {
        return $query
            ->where('is_closed', false)
            ->where(function (Builder $query) use ($tahunAkademik) {
                if ($tahunAkademik) {
                    $query->where('tahun_akademik_id', $tahunAkademik->id)
                        ->orWhereNull('tahun_akademik_id');
                } else {
                    $query->whereRaw('1 = 0');
                }
            });
    }
}
