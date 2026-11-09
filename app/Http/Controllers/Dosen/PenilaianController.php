<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Services\PenilaianService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PenilaianController extends Controller
{
    protected $penilaianService;

    public function __construct(PenilaianService $penilaianService)
    {
        $this->penilaianService = $penilaianService;
    }

    public function index(Request $request)
    {
        $query = $this->accessibleKelasQuery()->with(['mataKuliah', 'krsDetail']);

        // Filter by semester
        if ($request->filled('semester')) {
            $query->whereHas('mataKuliah', fn ($q) => $q->where('semester', $request->semester));
        }

        // Search by nama mk or kode mk
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('mataKuliah', function ($q) use ($search) {
                $q->where('nama_mk', 'like', "%{$search}%")
                    ->orWhere('kode_mk', 'like', "%{$search}%");
            });
        }

        $kelasAjar = $query->get();

        // Group by semester
        $kelasGrouped = $kelasAjar->groupBy(fn ($k) => $k->mataKuliah->semester);

        // Get available semesters from already loaded data (avoid N+1)
        $semesterList = $this->accessibleKelasQuery()
            ->with('mataKuliah')
            ->get()
            ->pluck('mataKuliah.semester')
            ->unique()
            ->sort()
            ->values();

        if ($request->ajax()) {
            return view('dosen.penilaian._cards', compact('kelasAjar', 'kelasGrouped'))->render();
        }

        return view('dosen.penilaian.index', compact('kelasAjar', 'kelasGrouped', 'semesterList'));
    }

    public function show($kelasId)
    {
        $kelas = $this->findAccessibleKelas($kelasId, [
            'mataKuliah',
            'krsDetail.krs.mahasiswa.user',
            'nilai',
        ]);

        return view('dosen.penilaian.show', compact('kelas'));
    }

    public function store(Request $request, $kelasId)
    {
        $kelas = $this->findAccessibleKelas($kelasId, ['krsDetail.krs']);

        $request->validate([
            'nilai' => 'required|array',
            'nilai.*' => 'nullable|numeric|min:0|max:100',
        ]);

        $mahasiswaTerdaftar = $kelas->krsDetail
            ->pluck('krs.mahasiswa_id')
            ->filter()
            ->map(fn ($mahasiswaId) => (string) $mahasiswaId)
            ->all();
        $mahasiswaYangDikirim = array_map('strval', array_keys($request->input('nilai')));

        abort_if(
            array_diff($mahasiswaYangDikirim, $mahasiswaTerdaftar) !== [],
            403,
            'Mahasiswa tidak terdaftar di kelas ini'
        );

        try {
            $this->penilaianService->bulkInputNilai($kelas->id, $request->nilai);

            return redirect()->back()->with('success', 'Nilai berhasil disimpan');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    private function findAccessibleKelas(int|string $kelasId, array $relations = []): Kelas
    {
        $kelas = $this->accessibleKelasQuery()
            ->with($relations)
            ->find($kelasId);

        abort_unless($kelas, 403, 'Anda tidak berhak mengakses kelas ini');

        return $kelas;
    }

    private function accessibleKelasQuery(): Builder
    {
        $user = Auth::user();

        if ($user->isAdminProdi()) {
            abort_unless($user->prodi_id, 403, 'Admin prodi belum memiliki program studi');

            return Kelas::query()->whereHas(
                'dosen.prodi',
                fn ($query) => $query->whereKey($user->prodi_id)
            );
        }

        if ($user->role === 'admin_fakultas') {
            abort_unless($user->fakultas_id, 403, 'Admin fakultas belum memiliki fakultas');

            return Kelas::query()->whereHas(
                'dosen.prodi',
                fn ($query) => $query->where('fakultas_id', $user->fakultas_id)
            );
        }

        $dosen = $user->dosen;

        if (! $dosen) {
            abort(403, 'Unauthorized');
        }

        return Kelas::query()->where('dosen_id', $dosen->id);
    }
}
