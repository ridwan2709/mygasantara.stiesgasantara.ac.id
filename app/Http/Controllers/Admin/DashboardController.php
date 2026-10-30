<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\MataKuliah;
use App\Models\Nilai;
use App\Models\Prodi;
use App\Models\TahunAkademik;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isSuperAdmin = $user->isSuperAdmin();
        $prodiId = $user->prodi_id;

        $fakultas = $isSuperAdmin ? null : $user->prodi?->fakultas;

        // Build scoped queries
        $prodiQuery = Prodi::query();
        $mahasiswaQuery = Mahasiswa::query();
        $dosenQuery = Dosen::query();
        $mataKuliahQuery = MataKuliah::query();
        $kelasQuery = Kelas::query();
        $nilaiQuery = Nilai::query();

        if (! $isSuperAdmin && $prodiId) {
            $prodiQuery->whereKey($prodiId);
            $mahasiswaQuery->where('prodi_id', $prodiId);
            $dosenQuery->where('prodi_id', $prodiId);
            $mataKuliahQuery->where('prodi_id', $prodiId);
            $kelasQuery->whereHas('dosen', fn ($q) => $q->where('prodi_id', $prodiId))
                ->whereHas('mataKuliah', fn ($q) => $q->where('prodi_id', $prodiId));
            $nilaiQuery->whereHas('mahasiswa', fn ($q) => $q->where('prodi_id', $prodiId));
        }

        // Basic counts (scoped)
        $stats = [
            'prodi' => (clone $prodiQuery)->count(),
            'mahasiswa' => (clone $mahasiswaQuery)->count(),
            'dosen' => (clone $dosenQuery)->count(),
            'mata_kuliah' => (clone $mataKuliahQuery)->count(),
            'kelas' => (clone $kelasQuery)->count(),
        ];

        // Only show fakultas count for superadmin
        if ($isSuperAdmin) {
            $stats['fakultas'] = Fakultas::count();
        }

        // Grade distribution (scoped)
        $gradeDistribution = (clone $nilaiQuery)
            ->selectRaw('nilai_huruf, COUNT(*) as count')
            ->groupBy('nilai_huruf')
            ->pluck('count', 'nilai_huruf')
            ->toArray();

        // Active academic year
        $activeYear = TahunAkademik::where('is_active', true)->first();

        // Per-prodi student count (scoped)
        $prodiStatsQuery = Prodi::withCount('mahasiswa');
        if (! $isSuperAdmin && $prodiId) {
            $prodiStatsQuery->whereKey($prodiId);
        }
        $prodiStats = $prodiStatsQuery
            ->orderBy('mahasiswa_count', 'desc')
            ->take(10)
            ->get();

        // Per-prodi dosen count (scoped)
        $dosenPerProdiQuery = Prodi::withCount('dosen');
        if (! $isSuperAdmin && $prodiId) {
            $dosenPerProdiQuery->whereKey($prodiId);
        }
        $dosenPerProdi = $dosenPerProdiQuery
            ->orderBy('dosen_count', 'desc')
            ->take(10)
            ->get();

        return view('admin.dashboard.index', compact(
            'stats', 'gradeDistribution', 'activeYear',
            'prodiStats', 'dosenPerProdi', 'isSuperAdmin', 'fakultas'
        ));
    }
}
