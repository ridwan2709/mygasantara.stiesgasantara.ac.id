<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\TahunAkademik;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class LmsController extends Controller
{
    /**
     * Show LMS dashboard with all kelas for dosen
     */
    public function index()
    {
        $user = Auth::user();

        $activeTA = TahunAkademik::active();

        $kelasQuery = $this->accessibleKelasQuery()
            ->with(['mataKuliah', 'tugas', 'jadwal.pertemuan.materiList']);

        if ($activeTA) {
            $kelasQuery->where(function ($query) use ($activeTA) {
                $query->where('tahun_akademik_id', $activeTA->id)
                    ->orWhereNull('tahun_akademik_id');
            });
        }

        $kelasList = $kelasQuery->get()
            ->map(function ($kelas) {
                $kelas->materi_count = $kelas->jadwal?->flatMap(fn ($jadwal) => $jadwal->pertemuan)->flatMap(fn ($pertemuan) => $pertemuan->materiList)->count() ?? 0;
                $kelas->tugas_count = $kelas->tugas->count();

                return $kelas;
            });

        $isAdminFakultas = $user->role === 'admin_fakultas' || $user->isAdminProdi();

        return view('dosen.lms.index', compact('kelasList', 'activeTA', 'isAdminFakultas'));
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
            abort(403, 'Anda tidak memiliki akses sebagai dosen.');
        }

        return Kelas::query()->where('dosen_id', $dosen->id);
    }
}
