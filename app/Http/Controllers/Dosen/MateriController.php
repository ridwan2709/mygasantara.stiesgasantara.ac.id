<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Materi;
use App\Models\Pertemuan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MateriController extends Controller
{
    /**
     * Show materi for a specific kelas
     */
    public function index($kelasId)
    {
        $kelas = $this->accessibleKelasQuery()
            ->with(['mataKuliah', 'jadwal.pertemuan.materiList'])
            ->findOrFail($kelasId);

        // Get all pertemuan for this kelas
        $pertemuanList = Pertemuan::whereHas('jadwalKuliah', fn($q) => $q->where('kelas_id', $kelasId))
            ->with('materiList')
            ->orderBy('pertemuan_ke')
            ->get();

        return view('dosen.materi.index', compact('kelas', 'pertemuanList'));
    }

    /**
     * Store new materi
     */
    public function store(Request $request, $kelasId)
    {
        $kelas = $this->accessibleKelasQuery()->findOrFail($kelasId);

        $validated = $request->validate([
            'pertemuan_id' => 'required|exists:pertemuan,id',
            'judul' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'file' => 'nullable|file|max:20480', // 20MB max
            'link_external' => 'nullable|url|max:500',
        ]);

        // Verify pertemuan belongs to this kelas
        $pertemuan = Pertemuan::where('id', $validated['pertemuan_id'])
            ->whereHas('jadwalKuliah', fn ($query) => $query->where('kelas_id', $kelas->id))
            ->firstOrFail();

        $materi = new Materi([
            'pertemuan_id' => $pertemuan->id,
            'judul' => $validated['judul'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'link_external' => $validated['link_external'] ?? null,
            'urutan' => $pertemuan->materiList()->count() + 1,
        ]);

        // Handle file upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs("materi/kelas_{$kelasId}", $filename);

            $materi->file_path = $path;
            $materi->file_name = $file->getClientOriginalName();
            $materi->file_size = $file->getSize();
            $materi->file_type = $file->getMimeType();
        }

        $materi->save();

        return back()->with('success', 'Materi berhasil ditambahkan.');
    }

    /**
     * Update materi
     */
    public function update(Request $request, $kelasId, Materi $materi)
    {
        $kelas = $this->accessibleKelasQuery()->findOrFail($kelasId);

        $materi = Materi::query()->whereKey($materi->id)
            ->whereHas('pertemuan.jadwalKuliah', fn ($query) => $query->where('kelas_id', $kelas->id))
            ->firstOrFail();

        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'file' => 'nullable|file|max:20480',
            'link_external' => 'nullable|url|max:500',
        ]);

        $materi->judul = $validated['judul'];
        $materi->deskripsi = $validated['deskripsi'] ?? null;
        $materi->link_external = $validated['link_external'] ?? null;

        // Handle new file upload
        if ($request->hasFile('file')) {
            // Delete old file
            if ($materi->file_path) {
                Storage::delete($materi->file_path);
            }

            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs("materi/kelas_{$kelasId}", $filename);

            $materi->file_path = $path;
            $materi->file_name = $file->getClientOriginalName();
            $materi->file_size = $file->getSize();
            $materi->file_type = $file->getMimeType();
        }

        $materi->save();

        return back()->with('success', 'Materi berhasil diupdate.');
    }

    /**
     * Delete materi
     */
    public function destroy($kelasId, Materi $materi)
    {
        $kelas = $this->accessibleKelasQuery()->findOrFail($kelasId);

        $materi = Materi::query()->whereKey($materi->id)
            ->whereHas('pertemuan.jadwalKuliah', fn ($query) => $query->where('kelas_id', $kelas->id))
            ->firstOrFail();

        // Delete file if exists
        if ($materi->file_path) {
            Storage::delete($materi->file_path);
        }

        $materi->delete();

        return back()->with('success', 'Materi berhasil dihapus.');
    }

    /**
     * Download materi file
     */
    public function download($kelasId, Materi $materi)
    {
        $kelas = $this->accessibleKelasQuery()->findOrFail($kelasId);

        $materi = Materi::query()->whereKey($materi->id)
            ->whereHas('pertemuan.jadwalKuliah', fn ($query) => $query->where('kelas_id', $kelas->id))
            ->firstOrFail();

        if (!$materi->file_path || !Storage::exists($materi->file_path)) {
            abort(404, 'File tidak ditemukan');
        }

        return Storage::download($materi->file_path, $materi->file_name);
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
        abort_unless($dosen, 403, 'Anda tidak memiliki akses sebagai dosen.');

        return Kelas::query()->where('dosen_id', $dosen->id);
    }
}
