<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\BimbinganSkripsi;
use App\Models\Skripsi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SkripsiController extends Controller
{
    public function index()
    {
        $skripsiList = $this->accessibleSkripsiQuery()
            ->with(['mahasiswa.user', 'pembimbing1.user', 'pembimbing2.user'])
            ->orderByRaw("CASE status
                WHEN 'bimbingan' THEN 1
                WHEN 'pengajuan' THEN 2
                WHEN 'review' THEN 3
                WHEN 'seminar_proposal' THEN 4
                WHEN 'penelitian' THEN 5
                WHEN 'seminar_hasil' THEN 6
                WHEN 'sidang' THEN 7
                WHEN 'revisi' THEN 8
                WHEN 'selesai' THEN 9
                WHEN 'ditolak' THEN 10
                WHEN 'diterima' THEN 11
                ELSE 12
            END")
            ->get();

        $pendingBimbingan = $this->accessibleBimbinganQuery()
            ->where('status', BimbinganSkripsi::STATUS_MENUNGGU)
            ->count();

        return view('dosen.skripsi.index', compact('skripsiList', 'pendingBimbingan'));
    }

    public function show(Skripsi $skripsi)
    {
        $this->authorizeSkripsi($skripsi);

        $skripsi->load(['mahasiswa.user', 'pembimbing1.user', 'pembimbing2.user', 'bimbingan.dosen.user']);

        return view('dosen.skripsi.show', compact('skripsi'));
    }

    public function reviewBimbingan(Request $request, BimbinganSkripsi $bimbingan)
    {
        $this->authorizeBimbingan($bimbingan);

        $validated = $request->validate([
            'catatan_dosen' => 'required|string',
            'status' => 'required|in:disetujui,revisi',
        ]);

        $bimbingan->update([
            'catatan_dosen' => $validated['catatan_dosen'],
            'status' => $validated['status'],
        ]);

        return redirect()->back()->with('success', 'Review bimbingan berhasil disimpan');
    }

    public function updateStatus(Request $request, Skripsi $skripsi)
    {
        $this->authorizeSkripsi($skripsi);

        $validated = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(Skripsi::getStatusList())),
        ]);

        $skripsi->update(['status' => $validated['status']]);

        return redirect()->back()->with('success', 'Status skripsi berhasil diupdate');
    }

    private function accessibleSkripsiQuery(): Builder
    {
        $user = Auth::user();

        if ($user->role === 'admin_fakultas') {
            abort_unless($user->fakultas_id, 403, 'Admin fakultas belum memiliki fakultas');

            return Skripsi::query()->whereHas(
                'mahasiswa.prodi',
                fn ($query) => $query->where('fakultas_id', $user->fakultas_id)
            );
        }

        $dosen = $user->dosen;

        if (! $dosen) {
            abort(403, 'Unauthorized');
        }

        return Skripsi::query()->where(function ($query) use ($dosen) {
            $query->where('pembimbing1_id', $dosen->id)
                ->orWhere('pembimbing2_id', $dosen->id);
        });
    }

    private function accessibleBimbinganQuery(): Builder
    {
        $user = Auth::user();

        if ($user->role === 'admin_fakultas') {
            abort_unless($user->fakultas_id, 403, 'Admin fakultas belum memiliki fakultas');

            return BimbinganSkripsi::query()->whereHas(
                'skripsi.mahasiswa.prodi',
                fn ($query) => $query->where('fakultas_id', $user->fakultas_id)
            );
        }

        $dosen = $user->dosen;

        if (! $dosen) {
            abort(403, 'Unauthorized');
        }

        return BimbinganSkripsi::query()->where('dosen_id', $dosen->id);
    }

    private function authorizeSkripsi(Skripsi $skripsi): void
    {
        abort_unless(
            $this->accessibleSkripsiQuery()->whereKey($skripsi->id)->exists(),
            403,
            'Anda tidak berhak mengakses skripsi ini'
        );
    }

    private function authorizeBimbingan(BimbinganSkripsi $bimbingan): void
    {
        abort_unless(
            $this->accessibleBimbinganQuery()->whereKey($bimbingan->id)->exists(),
            403,
            'Anda tidak berhak mengakses bimbingan ini'
        );
    }
}
