<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dosen;
use App\Models\DosenDokumen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DosenDokumenController extends Controller
{
    public function index(Request $request, Dosen $dosen): View
    {
        $this->ensureDosenIsInScope($request, $dosen);

        $dosen->load(['user', 'prodi']);
        $dokumen = $dosen->dokumenPenunjang()->latest()->get();

        return view('admin.dosen.dokumen', compact('dosen', 'dokumen'));
    }

    public function store(Request $request, Dosen $dosen): RedirectResponse
    {
        $this->ensureDosenIsInScope($request, $dosen);

        $validated = $request->validate([
            'nama_dokumen' => ['required', 'string', 'max:100'],
            'link_url' => [
                'required',
                'url',
                'max:2048',
                'regex:/^https:\/\/(?:drive|docs)\.google\.com\//i',
            ],
        ]);

        $dosen->dokumenPenunjang()->create([
            'nama_dokumen' => $validated['nama_dokumen'],
            'link_url' => $validated['link_url'],
        ]);

        return redirect()
            ->route('admin.dosen.dokumen.index', $dosen)
            ->with('success', 'Tautan dokumen penunjang berhasil disimpan.');
    }

    public function destroy(Request $request, Dosen $dosen, DosenDokumen $dokumen): RedirectResponse
    {
        $this->ensureDosenIsInScope($request, $dosen);
        abort_unless($dokumen->dosen_id === $dosen->id, 404);

        $dokumen->delete();

        return redirect()
            ->route('admin.dosen.dokumen.index', $dosen)
            ->with('success', 'Tautan dokumen penunjang berhasil dihapus.');
    }

    private function ensureDosenIsInScope(Request $request, Dosen $dosen): void
    {
        if ($request->boolean('fakultas_scoped') && $request->filled('fakultas_scope')) {
            abort_unless(
                $dosen->prodi()->where('fakultas_id', $request->integer('fakultas_scope'))->exists(),
                404
            );
        }
    }
}
