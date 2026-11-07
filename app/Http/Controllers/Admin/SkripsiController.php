<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dosen;
use App\Models\Skripsi;
use Illuminate\Http\Request;

class SkripsiController extends Controller
{
    public function index(Request $request)
    {
        $query = Skripsi::with(['mahasiswa.user', 'mahasiswa.prodi', 'pembimbing1.user', 'pembimbing2.user']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                    ->orWhereHas('mahasiswa.user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('mahasiswa', fn ($q) => $q->where('nim', 'like', "%{$search}%"));
            });
        }

        if ($request->get('prodi_scoped') && $request->get('prodi_scope')) {
            $prodiId = $request->get('prodi_scope');
            $query->whereHas('mahasiswa.prodi', fn ($q) => $q->where('id', $prodiId));
        }

        // Sorting
        $sortColumn = $request->get('sort', 'created_at');
        $sortDirection = $request->get('order', 'desc');

        if ($sortColumn === 'mahasiswa_name') {
            $query->join('mahasiswa', 'skripsi.mahasiswa_id', '=', 'mahasiswa.id')
                ->join('users', 'mahasiswa.user_id', '=', 'users.id')
                ->select('skripsi.*')
                ->orderBy('users.name', $sortDirection);
        } elseif ($sortColumn === 'mahasiswa_nim') {
            $query->join('mahasiswa', 'skripsi.mahasiswa_id', '=', 'mahasiswa.id')
                ->select('skripsi.*')
                ->orderBy('mahasiswa.nim', $sortDirection);
        } elseif ($sortColumn === 'pembimbing_name') {
            $query->leftJoin('dosen', 'skripsi.pembimbing1_id', '=', 'dosen.id')
                ->leftJoin('users', 'dosen.user_id', '=', 'users.id')
                ->select('skripsi.*')
                ->orderBy('users.name', $sortDirection);
        } elseif (in_array($sortColumn, ['judul', 'status', 'created_at'])) {
            $query->orderBy($sortColumn, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $skripsiList = $query->paginate(20)->withQueryString();

        // Scope dosen list for dropdown
        $dosenQuery = Dosen::with('user');
        if ($request->get('prodi_scoped') && $request->get('prodi_scope')) {
            $dosenQuery->where('prodi_id', $request->get('prodi_scope'));
        }
        $dosenList = $dosenQuery->get();
        $statusList = Skripsi::getStatusList();

        // Stats - also scoped
        $statsQuery = Skripsi::query();
        if ($request->get('prodi_scoped') && $request->get('prodi_scope')) {
            $statsQuery->whereHas('mahasiswa.prodi', fn ($q) => $q->where('id', $request->get('prodi_scope')));
        }
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'aktif' => (clone $statsQuery)->active()->count(),
            'menunggu_pembimbing' => (clone $statsQuery)->whereNull('pembimbing1_id')->count(),
            'selesai' => (clone $statsQuery)->where('status', Skripsi::STATUS_SELESAI)->count(),
        ];

        return view('admin.skripsi.index', compact('skripsiList', 'dosenList', 'statusList', 'stats'));
    }

    public function show(Skripsi $skripsi)
    {
        $this->ensureProdiScope($skripsi);
        $skripsi->load(['mahasiswa.user', 'pembimbing1.user', 'pembimbing2.user', 'bimbingan.dosen.user']);
        $dosenList = Dosen::with('user')
            ->when(auth()->user()->role === 'admin_prodi', fn ($query) => $query->where('prodi_id', auth()->user()->prodi_id))
            ->get();

        return view('admin.skripsi.show', compact('skripsi', 'dosenList'));
    }

    public function assignPembimbing(Request $request, Skripsi $skripsi)
    {
        $this->ensureProdiScope($skripsi);
        $validated = $request->validate([
            'pembimbing1_id' => 'required|exists:dosen,id',
            'pembimbing2_id' => 'nullable|exists:dosen,id|different:pembimbing1_id',
        ]);
        abort_unless(
            auth()->user()->role !== 'admin_prodi'
                || Dosen::whereKey($validated['pembimbing1_id'])->where('prodi_id', auth()->user()->prodi_id)->exists()
                    && (empty($validated['pembimbing2_id']) || Dosen::whereKey($validated['pembimbing2_id'])->where('prodi_id', auth()->user()->prodi_id)->exists()),
            404
        );

        $skripsi->update([
            'pembimbing1_id' => $validated['pembimbing1_id'],
            'pembimbing2_id' => $validated['pembimbing2_id'] ?? null,
            'status' => Skripsi::STATUS_DITERIMA,
            'tanggal_acc_judul' => now(),
        ]);

        return redirect()->back()->with('success', 'Pembimbing berhasil ditentukan');
    }

    public function updateStatus(Request $request, Skripsi $skripsi)
    {
        $this->ensureProdiScope($skripsi);
        $validated = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(Skripsi::getStatusList())),
            'catatan_admin' => 'nullable|string',
        ]);

        $updateData = ['status' => $validated['status']];

        if (! empty($validated['catatan_admin'])) {
            $updateData['catatan_admin'] = $validated['catatan_admin'];
        }

        // Update milestone dates based on status
        $dateFields = [
            Skripsi::STATUS_DITERIMA => 'tanggal_acc_judul',
            Skripsi::STATUS_SEMINAR_PROPOSAL => 'tanggal_seminar_proposal',
            Skripsi::STATUS_SEMINAR_HASIL => 'tanggal_seminar_hasil',
            Skripsi::STATUS_SIDANG => 'tanggal_sidang',
            Skripsi::STATUS_SELESAI => 'tanggal_selesai',
        ];

        if (isset($dateFields[$validated['status']]) && empty($skripsi->{$dateFields[$validated['status']]})) {
            $updateData[$dateFields[$validated['status']]] = now();
        }

        $skripsi->update($updateData);

        return redirect()->back()->with('success', 'Status skripsi berhasil diupdate');
    }

    public function updateNilai(Request $request, Skripsi $skripsi)
    {
        $this->ensureProdiScope($skripsi);
        $validated = $request->validate([
            'nilai_akhir' => 'required|numeric|min:0|max:100',
            'nilai_huruf' => 'required|in:A,B+,B,C+,C,D,E',
        ]);

        $skripsi->update([
            'nilai_akhir' => $validated['nilai_akhir'],
            'nilai_huruf' => $validated['nilai_huruf'],
            'status' => Skripsi::STATUS_SELESAI,
            'tanggal_selesai' => now(),
        ]);

        return redirect()->back()->with('success', 'Nilai skripsi berhasil disimpan');
    }

    private function ensureProdiScope(Skripsi $skripsi): void
    {
        if (auth()->user()->role === 'admin_prodi') {
            abort_unless(
                $skripsi->mahasiswa()->where('prodi_id', auth()->user()->prodi_id)->exists(),
                404
            );
        }
    }
}
