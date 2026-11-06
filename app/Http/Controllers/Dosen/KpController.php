<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\KerjaPraktek;
use App\Models\LogbookKp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KpController extends Controller
{
    public function index()
    {
        $dosen = Auth::user()->dosen;
        $kpList = $this->accessibleKpQuery()
            ->with(['mahasiswa.user'])
            ->orderByRaw("CASE status
                WHEN 'berlangsung' THEN 1
                WHEN 'pengajuan' THEN 2
                WHEN 'disetujui' THEN 3
                WHEN 'selesai_kp' THEN 4
                WHEN 'penyusunan_laporan' THEN 5
                WHEN 'seminar' THEN 6
                WHEN 'revisi' THEN 7
                WHEN 'selesai' THEN 8
                WHEN 'ditolak' THEN 9
                ELSE 10
            END")
            ->get();

        $pendingLogbook = $this->accessibleLogbookQuery()
            ->where('status', LogbookKp::STATUS_PENDING)
            ->count();

        return view('dosen.kp.index', compact('dosen', 'kpList', 'pendingLogbook'));
    }

    public function show(KerjaPraktek $kp)
    {
        $dosen = Auth::user()->dosen;
        $this->authorizeKp($kp);

        $kp->load(['mahasiswa.user', 'pembimbing.user', 'logbook']);

        return view('dosen.kp.show', compact('dosen', 'kp'));
    }

    public function reviewLogbook(Request $request, LogbookKp $logbook)
    {
        abort_unless(
            $this->accessibleLogbookQuery()->whereKey($logbook->id)->exists(),
            403,
            'Anda tidak berhak mengakses logbook ini'
        );

        $validated = $request->validate([
            'catatan_pembimbing' => 'nullable|string',
            'status' => 'required|in:disetujui,revisi',
        ]);

        $logbook->update($validated);

        return redirect()->back()->with('success', 'Logbook berhasil direview');
    }

    public function updateStatus(Request $request, KerjaPraktek $kp)
    {
        $this->authorizeKp($kp);

        $validated = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(KerjaPraktek::getStatusList())),
        ]);

        $kp->update(['status' => $validated['status']]);

        return redirect()->back()->with('success', 'Status berhasil diupdate');
    }

    private function accessibleKpQuery(): Builder
    {
        $user = Auth::user();

        if ($user->role === 'admin_fakultas') {
            abort_unless($user->fakultas_id, 403, 'Admin fakultas belum memiliki fakultas');

            return KerjaPraktek::query()->whereHas(
                'mahasiswa.prodi',
                fn ($query) => $query->where('fakultas_id', $user->fakultas_id)
            );
        }

        $dosen = $user->dosen;

        if (! $dosen) {
            abort(403, 'Unauthorized');
        }

        return KerjaPraktek::query()->where('pembimbing_id', $dosen->id);
    }

    private function accessibleLogbookQuery(): Builder
    {
        $user = Auth::user();

        if ($user->role === 'admin_fakultas') {
            abort_unless($user->fakultas_id, 403, 'Admin fakultas belum memiliki fakultas');

            return LogbookKp::query()->whereHas(
                'kerjaPraktek.mahasiswa.prodi',
                fn ($query) => $query->where('fakultas_id', $user->fakultas_id)
            );
        }

        $dosen = $user->dosen;

        if (! $dosen) {
            abort(403, 'Unauthorized');
        }

        return LogbookKp::query()->whereHas(
            'kerjaPraktek',
            fn ($query) => $query->where('pembimbing_id', $dosen->id)
        );
    }

    private function authorizeKp(KerjaPraktek $kp): void
    {
        abort_unless(
            $this->accessibleKpQuery()->whereKey($kp->id)->exists(),
            403,
            'Anda tidak berhak mengakses kerja praktek ini'
        );
    }
}
