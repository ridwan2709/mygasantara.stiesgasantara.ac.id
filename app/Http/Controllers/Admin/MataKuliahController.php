<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fakultas;
use App\Models\MataKuliah;
use App\Models\Prodi;
use App\Services\AkademikService;
use Illuminate\Http\Request;

class MataKuliahController extends Controller
{
    protected $akademikService;

    public function __construct(AkademikService $akademikService)
    {
        $this->akademikService = $akademikService;
    }

    public function index(Request $request)
    {
        $query = MataKuliah::with('prodi.fakultas');

        // 1. Filter Category (Prefix)
        if ($request->filled('category')) {
            $query->where('kode_mk', 'like', $request->category.'%');
        }

        // 2. Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_mk', 'like', "%{$search}%")
                    ->orWhere('kode_mk', 'like', "%{$search}%");
            });
        }

        if ($request->get('prodi_scoped') && $request->get('prodi_scope')) {
            $query->where('prodi_id', $request->get('prodi_scope'));
        }

        // 4. Sorting
        $sortColumn = $request->get('sort', 'kode_mk');
        $sortDirection = $request->get('order', 'asc');

        $allowedSorts = ['kode_mk', 'nama_mk', 'sks', 'semester', 'created_at'];
        if (in_array($sortColumn, $allowedSorts)) {
            $query->orderBy($sortColumn, $sortDirection);
        } else {
            $query->orderBy('kode_mk', 'asc');
        }

        // 5. Pagination
        $mataKuliah = $query->paginate(config('siakad.pagination', 15))->withQueryString();

        // User info for view
        $isSuperAdmin = auth()->user()->isSuperAdmin();

        // Fakultas list for superadmin dropdown
        $fakultasList = $isSuperAdmin ? Fakultas::all() : collect();

        // Prodi list for dropdown (scoped)
        $prodiQuery = Prodi::with('fakultas');
        if ($request->get('prodi_scoped') && $request->get('prodi_scope')) {
            $prodiQuery->whereKey($request->get('prodi_scope'));
        }
        $prodiList = $prodiQuery->get();

        return view('admin.mata-kuliah.index', compact('mataKuliah', 'prodiList', 'fakultasList', 'isSuperAdmin'));
    }

    public function export(Request $request)
    {
        $query = MataKuliah::query();

        if ($request->filled('category')) {
            $query->where('kode_mk', 'like', $request->category.'%');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_mk', 'like', "%{$search}%")
                    ->orWhere('kode_mk', 'like', "%{$search}%");
            });
        }

        if ($request->get('prodi_scoped') && $request->get('prodi_scope')) {
            $query->where('prodi_id', $request->get('prodi_scope'));
        }

        $sortColumn = $request->get('sort', 'kode_mk');
        $sortDirection = $request->get('order', 'asc');
        $allowedSorts = ['kode_mk', 'nama_mk', 'sks', 'semester', 'created_at'];

        if (in_array($sortColumn, $allowedSorts)) {
            $query->orderBy($sortColumn, $sortDirection);
        }

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Kode MK', 'Nama Mata Kuliah', 'SKS', 'Semester', 'Prodi', 'Dibuat Pada']);

            $query->with('prodi')->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->kode_mk,
                        $row->nama_mk,
                        $row->sks,
                        $row->semester,
                        $row->prodi?->nama ?? '-',
                        $row->created_at,
                    ]);
                }
            });

            fclose($handle);
        }, 'data-mata-kuliah-'.date('Y-m-d-H-i').'.csv');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_mk' => 'required|string|unique:mata_kuliah,kode_mk',
            'nama_mk' => 'required|string',
            'sks' => 'required|integer|min:1',
            'semester' => 'required|integer|min:1',
            'prodi_id' => 'nullable|exists:prodi,id',
        ]);

        if ($request->user()->role === 'admin_prodi') {
            $validated['prodi_id'] = $request->user()->prodi_id;
        }

        MataKuliah::create($validated);

        return redirect()->back()->with('success', 'Mata Kuliah berhasil ditambahkan');
    }

    public function update(Request $request, MataKuliah $mataKuliah)
    {
        $this->ensureProdiScope($mataKuliah);
        $validated = $request->validate([
            'kode_mk' => 'required|string|unique:mata_kuliah,kode_mk,'.$mataKuliah->id,
            'nama_mk' => 'required|string',
            'sks' => 'required|integer|min:1',
            'semester' => 'required|integer|min:1',
            'prodi_id' => 'nullable|exists:prodi,id',
        ]);

        if ($request->user()->role === 'admin_prodi') {
            $validated['prodi_id'] = $request->user()->prodi_id;
        }

        $mataKuliah->update($validated);

        return redirect()->back()->with('success', 'Mata Kuliah berhasil diupdate');
    }

    public function destroy(MataKuliah $mataKuliah)
    {
        $this->ensureProdiScope($mataKuliah);
        // Check if mata kuliah has kelas
        if ($mataKuliah->kelas()->exists()) {
            return redirect()->back()->withErrors(['error' => 'Tidak dapat menghapus mata kuliah yang memiliki kelas.']);
        }

        $mataKuliah->delete();

        return redirect()->back()->with('success', 'Mata Kuliah berhasil dihapus');
    }

    private function ensureProdiScope(MataKuliah $mataKuliah): void
    {
        if (auth()->user()->role === 'admin_prodi') {
            abort_unless((int) $mataKuliah->prodi_id === (int) auth()->user()->prodi_id, 404);
        }
    }
}
