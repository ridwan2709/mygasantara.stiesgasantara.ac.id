<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dosen;
use App\Models\Kelas;
use App\Models\MataKuliah;
use App\Services\AkademikService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class KelasController extends Controller
{
    protected $akademikService;

    public function __construct(AkademikService $akademikService)
    {
        $this->akademikService = $akademikService;
    }

    public function index(Request $request)
    {
        $query = Kelas::with(['mataKuliah', 'dosen.user', 'dosen.prodi', 'jadwal']);

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_kelas', 'like', "%{$search}%")
                    ->orWhereHas('mataKuliah', fn ($q2) => $q2->where('nama_mk', 'like', "%{$search}%")->orWhere('kode_mk', 'like', "%{$search}%"))
                    ->orWhereHas('dosen.user', fn ($q3) => $q3->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->get('prodi_scoped') && $request->get('prodi_scope')) {
            $prodiId = $request->get('prodi_scope');
            $query->whereHas('dosen', fn ($q) => $q->where('prodi_id', $prodiId))
                ->whereHas('mataKuliah', fn ($q) => $q->where('prodi_id', $prodiId));
        }

        // Sorting
        $sortColumn = $request->get('sort', 'nama_kelas');
        $sortDirection = $request->get('order', 'asc');

        if ($sortColumn === 'mata_kuliah') {
            $query->join('mata_kuliah', 'kelas.mata_kuliah_id', '=', 'mata_kuliah.id')
                ->select('kelas.*')
                ->orderBy('mata_kuliah.nama_mk', $sortDirection);
        } elseif ($sortColumn === 'dosen') {
            $query->join('dosen', 'kelas.dosen_id', '=', 'dosen.id')
                ->join('users', 'dosen.user_id', '=', 'users.id')
                ->select('kelas.*')
                ->orderBy('users.name', $sortDirection);
        } elseif (in_array($sortColumn, ['nama_kelas', 'kapasitas'])) {
            $query->orderBy($sortColumn, $sortDirection);
        } else {
            $query->orderBy('nama_kelas', 'asc');
        }

        $kelas = $query->paginate(config('siakad.pagination', 15))->withQueryString();
        $mataKuliahQuery = MataKuliah::query();
        if ($request->get('prodi_scoped') && $request->get('prodi_scope')) {
            $mataKuliahQuery->where('prodi_id', $request->get('prodi_scope'));
        }
        $mataKuliah = $mataKuliahQuery->orderBy('kode_mk')->get();

        // Scope dosen list for dropdown
        $dosenQuery = Dosen::with('user');
        if ($request->get('prodi_scoped') && $request->get('prodi_scope')) {
            $dosenQuery->where('prodi_id', $request->get('prodi_scope'));
        }
        $dosen = $dosenQuery->get();

        return view('admin.kelas.index', compact('kelas', 'mataKuliah', 'dosen'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'mata_kuliah_id' => 'required|exists:mata_kuliah,id',
            'dosen_id' => 'required|exists:dosen,id',
            'nama_kelas' => 'required|string',
            'kapasitas' => 'nullable|integer|min:1',
        ]);

        $this->ensureRelatedDataInProdi($request, $validated);

        $kelas = $this->akademikService->createKelas($validated);

        return redirect()->back()->with('success', 'Kelas berhasil ditambahkan');
    }

    public function update(Request $request, Kelas $kelas)
    {
        $this->ensureKelasScope($kelas);
        $validated = $request->validate([
            'mata_kuliah_id' => 'required|exists:mata_kuliah,id',
            'dosen_id' => 'required|exists:dosen,id',
            'nama_kelas' => 'required|string',
            'kapasitas' => 'nullable|integer|min:1',
        ]);

        $this->ensureRelatedDataInProdi($request, $validated);

        $kelas->update([
            'mata_kuliah_id' => $validated['mata_kuliah_id'],
            'dosen_id' => $validated['dosen_id'],
            'nama_kelas' => $validated['nama_kelas'],
            'kapasitas' => $validated['kapasitas'],
        ]);

        return redirect()->back()->with('success', 'Kelas berhasil diupdate');
    }

    public function updateJadwal(Request $request, Kelas $kelas)
    {
        $this->ensureKelasScope($kelas);
        $validated = $request->validate([
            'hari' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i',
            'ruangan' => 'nullable|string|max:50',
        ]);

        $oldJadwal = $kelas->jadwal()->first();
        $changes = [];

        if ($oldJadwal) {
            if ($oldJadwal->hari !== $validated['hari']) {
                $changes['hari'] = ['old' => $oldJadwal->hari, 'new' => $validated['hari']];
            }

            $oldJam = Carbon::parse($oldJadwal->jam_mulai)->format('H:i').'-'.Carbon::parse($oldJadwal->jam_selesai)->format('H:i');
            $newJam = $validated['jam_mulai'].'-'.$validated['jam_selesai'];
            if ($oldJam !== $newJam) {
                $changes['jam'] = ['old' => $oldJam, 'new' => $newJam];
            }

            if (($oldJadwal->ruangan ?? '') !== ($validated['ruangan'] ?? '')) {
                $changes['ruangan'] = ['old' => $oldJadwal->ruangan ?? '-', 'new' => $validated['ruangan'] ?? '-'];
            }
        }

        $jadwal = $kelas->jadwal()->updateOrCreate(
            ['kelas_id' => $kelas->id],
            [
                'hari' => $validated['hari'],
                'jam_mulai' => $validated['jam_mulai'],
                'jam_selesai' => $validated['jam_selesai'],
                'ruangan' => $validated['ruangan'] ?? null,
            ]
        );

        if (! empty($changes)) {
            $kelas->load('mataKuliah');
            $notificationService = app(NotificationService::class);
            $count = $notificationService->notifyJadwalChange($kelas, $jadwal, $changes);

            if ($count > 0) {
                return redirect()->back()->with('success', "Jadwal berhasil diupdate. Notifikasi terkirim ke {$count} mahasiswa.");
            }
        }

        return redirect()->back()->with('success', 'Jadwal kelas berhasil disimpan');
    }

    public function destroy(Kelas $kelas)
    {
        $this->ensureKelasScope($kelas);
        $kelas->delete();

        return redirect()->back()->with('success', 'Kelas berhasil dihapus');
    }

    private function ensureRelatedDataInProdi(Request $request, array $validated): void
    {
        if ($request->user()->role !== 'admin_prodi') {
            return;
        }

        $prodiId = $request->user()->prodi_id;
        abort_unless(
            Dosen::whereKey($validated['dosen_id'])->where('prodi_id', $prodiId)->exists()
                && MataKuliah::whereKey($validated['mata_kuliah_id'])->where('prodi_id', $prodiId)->exists(),
            404
        );
    }

    private function ensureKelasScope(Kelas $kelas): void
    {
        if (auth()->user()->role === 'admin_prodi') {
            abort_unless(
                $kelas->dosen()->where('prodi_id', auth()->user()->prodi_id)->exists()
                    && $kelas->mataKuliah()->where('prodi_id', auth()->user()->prodi_id)->exists(),
                404
            );
        }
    }
}
