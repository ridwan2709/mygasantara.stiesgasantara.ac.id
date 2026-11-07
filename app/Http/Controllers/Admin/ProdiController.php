<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fakultas;
use App\Models\Prodi;
use App\Services\AkademikService;
use Illuminate\Http\Request;

class ProdiController extends Controller
{
    protected $akademikService;

    public function __construct(AkademikService $akademikService)
    {
        $this->akademikService = $akademikService;
    }

    public function index()
    {
        $user = auth()->user();

        $query = Fakultas::with(['prodi' => function ($q) use ($user) {
            $q->withCount(['mahasiswa', 'dosen']);
            if ($user->role === 'admin_prodi') {
                $q->whereKey($user->prodi_id);
            }
        }]);

        // Scope to user's fakultas if not superadmin
        if ($user->role === 'admin_prodi') {
            $query->where('id', $user->prodi?->fakultas_id);
        }

        $fakultas = $query->get();
        $isSuperAdmin = $user->isSuperAdmin();

        return view('admin.prodi.index', compact('fakultas', 'isSuperAdmin'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
        $validated = $request->validate([
            'fakultas_id' => 'required|exists:fakultas,id',
            'nama' => 'required|string|max:255',
        ]);

        // Check if user can create prodi in this fakultas
        $user = auth()->user();
        if (! $user->isSuperAdmin() && $user->fakultas_id != $validated['fakultas_id']) {
            abort(response()->view('errors.403', ['message' => 'Anda tidak dapat menambah prodi di fakultas ini.'], 403));
        }

        $this->akademikService->createProdi($validated);

        return redirect()->back()->with('success', 'Prodi berhasil ditambahkan');
    }

    public function update(Request $request, Prodi $prodi)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $validated = $request->validate([
            'fakultas_id' => 'required|exists:fakultas,id',
            'nama' => 'required|string|max:255',
        ]);

        // Check if user can move prodi to target fakultas
        $user = auth()->user();
        if (! $user->isSuperAdmin() && $user->fakultas_id != $validated['fakultas_id']) {
            abort(response()->view('errors.403', ['message' => 'Anda tidak dapat memindahkan prodi ke fakultas lain.'], 403));
        }

        $prodi->update($validated);

        return redirect()->back()->with('success', 'Prodi berhasil diupdate');
    }

    public function destroy(Prodi $prodi)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $prodi->delete();

        return redirect()->back()->with('success', 'Prodi berhasil dihapus');
    }
}
