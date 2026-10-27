<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dosen;
use App\Models\KehadiranDosen;
use App\Models\PengaturanGpsKehadiran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KehadiranDosenController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $dosenId = $request->get('dosen_id');

        $query = KehadiranDosen::with(['dosen.user', 'dosen.prodi', 'jadwalKuliah.kelas.mataKuliah'])
            ->whereYear('tanggal', $year)
            ->whereMonth('tanggal', $month);

        if ($dosenId) {
            $query->where('dosen_id', $dosenId);
        }

        if ($request->get('prodi_scoped') && $request->get('prodi_scope')) {
            $query->whereHas('dosen', fn ($q) => $q->where('prodi_id', $request->get('prodi_scope')));
        }

        $kehadiranList = $query->orderBy('tanggal', 'desc')->paginate(50);

        // Stats - also scoped
        $statsQuery = KehadiranDosen::whereYear('tanggal', $year)->whereMonth('tanggal', $month);
        if ($dosenId) {
            $statsQuery->where('dosen_id', $dosenId);
        }
        if ($request->get('prodi_scoped') && $request->get('prodi_scope')) {
            $statsQuery->whereHas('dosen', fn ($q) => $q->where('prodi_id', $request->get('prodi_scope')));
        }

        $stats = $statsQuery->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Scope dosen list for dropdown
        $dosenQuery = Dosen::with('user');
        if ($request->get('prodi_scoped') && $request->get('prodi_scope')) {
            $dosenQuery->where('prodi_id', $request->get('prodi_scope'));
        }
        $dosenList = $dosenQuery->get();

        // Rekap per dosen - also scoped
        $rekapQuery = KehadiranDosen::whereYear('tanggal', $year)
            ->whereMonth('tanggal', $month);
        if ($request->get('prodi_scoped') && $request->get('prodi_scope')) {
            $rekapQuery->whereHas('dosen', fn ($q) => $q->where('prodi_id', $request->get('prodi_scope')));
        }
        $rekapDosen = $rekapQuery->selectRaw('dosen_id, status, COUNT(*) as count')
            ->groupBy('dosen_id', 'status')
            ->get()
            ->groupBy('dosen_id');

        return view('admin.kehadiran-dosen.index', compact('kehadiranList', 'stats', 'dosenList', 'rekapDosen', 'month', 'year', 'dosenId'));
    }

    public function show(Dosen $dosen, Request $request)
    {
        abort_unless(
            auth()->user()->role !== 'admin_prodi'
                || (int) $dosen->prodi_id === (int) auth()->user()->prodi_id,
            404
        );
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $kehadiranList = KehadiranDosen::where('dosen_id', $dosen->id)
            ->whereYear('tanggal', $year)
            ->whereMonth('tanggal', $month)
            ->with('jadwalKuliah.kelas.mataKuliah')
            ->orderBy('tanggal', 'desc')
            ->get();

        $stats = $kehadiranList->groupBy('status')->map->count();

        return view('admin.kehadiran-dosen.show', compact('dosen', 'kehadiranList', 'stats', 'month', 'year'));
    }

    public function updateGpsSettings(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meter' => ['required', 'integer', 'between:10,5000'],
        ]);

        PengaturanGpsKehadiran::query()->updateOrCreate(
            ['id' => 1],
            $validated,
        );

        return redirect()->route('admin.kehadiran-gps.edit')->with('success', 'Pengaturan GPS presensi berhasil disimpan.');
    }
}
