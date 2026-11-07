<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\JadwalKuliah;
use App\Models\Kelas;
use App\Models\Pertemuan;
use App\Services\PresensiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PresensiController extends Controller
{
    protected PresensiService $presensiService;

    public function __construct(PresensiService $presensiService)
    {
        $this->presensiService = $presensiService;
    }

    /**
     * Daftar kelas yang diampu
     */
    public function index()
    {
        $kelasList = $this->accessibleKelasQuery()
            ->with(['mataKuliah', 'jadwal'])
            ->withCount(['krsDetail as jumlah_mahasiswa' => function ($query) {
                $query->whereHas('krs', fn ($krsQuery) => $krsQuery->where('status', 'approved'));
            }])
            ->get();

        // Group by semester
        $kelasGrouped = $kelasList->groupBy(fn ($k) => $k->mataKuliah->semester ?? 0);

        // Get semester list for filter
        $semesterList = $kelasList->pluck('mataKuliah.semester')->unique()->sort()->values();

        return view('dosen.presensi.index', compact('kelasList', 'kelasGrouped', 'semesterList'));
    }

    /**
     * Detail presensi per kelas
     */
    public function showKelas(Kelas $kelas)
    {
        $this->authorizeKelas($kelas);

        $kelas->load(['mataKuliah', 'jadwal']);
        $pertemuanList = $this->presensiService->getPertemuanByKelas($kelas->id);
        $rekapMahasiswa = $this->presensiService->getPresensiByKelas($kelas->id);

        return view('dosen.presensi.kelas', compact('kelas', 'pertemuanList', 'rekapMahasiswa'));
    }

    /**
     * Form buat pertemuan baru
     */
    public function createPertemuan(Kelas $kelas)
    {
        $this->authorizeKelas($kelas);

        $kelas->load(['mataKuliah', 'jadwal']);
        $jadwalList = $kelas->jadwal;
        $nextPertemuanKe = [];

        foreach ($jadwalList as $jadwal) {
            $nextPertemuanKe[$jadwal->id] = $this->presensiService->getNextPertemuanKe($jadwal->id);
        }

        return view('dosen.presensi.pertemuan-create', compact('kelas', 'jadwalList', 'nextPertemuanKe'));
    }

    /**
     * Simpan pertemuan baru
     */
    public function storePertemuan(Request $request, Kelas $kelas)
    {
        $this->authorizeKelas($kelas);

        $validated = $request->validate([
            'jadwal_kuliah_id' => 'required|exists:jadwal_kuliah,id',
            'pertemuan_ke' => 'required|integer|min:1|max:16',
            'tanggal' => 'required|date',
            'materi' => 'nullable|string|max:255',
        ]);

        // Check jadwal belongs to this kelas
        $jadwal = JadwalKuliah::findOrFail($validated['jadwal_kuliah_id']);
        if ($jadwal->kelas_id !== $kelas->id) {
            abort(403, 'Jadwal tidak valid');
        }

        // Check duplicate pertemuan_ke
        $exists = Pertemuan::where('jadwal_kuliah_id', $validated['jadwal_kuliah_id'])
            ->where('pertemuan_ke', $validated['pertemuan_ke'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['pertemuan_ke' => 'Pertemuan ke-'.$validated['pertemuan_ke'].' sudah ada'])
                ->withInput();
        }

        $pertemuan = $this->presensiService->createPertemuan(
            $validated['jadwal_kuliah_id'],
            $validated['pertemuan_ke'],
            $validated['tanggal'],
            $validated['materi']
        );

        return redirect()->route('dosen.presensi.input', $pertemuan)
            ->with('success', 'Pertemuan berhasil dibuat. Silakan input presensi.');
    }

    /**
     * Form input presensi
     */
    public function inputPresensi(Pertemuan $pertemuan)
    {
        $kelas = $pertemuan->jadwalKuliah->kelas;
        $this->authorizeKelas($kelas);

        $pertemuan->load(['jadwalKuliah.kelas.mataKuliah', 'presensi']);
        $mahasiswaList = $this->presensiService->getMahasiswaByKelas($kelas->id);

        // Get existing presensi data
        $existingPresensi = $pertemuan->presensi->keyBy('mahasiswa_id');

        return view('dosen.presensi.input', compact('pertemuan', 'kelas', 'mahasiswaList', 'existingPresensi'));
    }

    /**
     * Simpan presensi
     */
    public function storePresensi(Request $request, Pertemuan $pertemuan)
    {
        $kelas = $pertemuan->jadwalKuliah->kelas;
        $this->authorizeKelas($kelas);

        $validated = $request->validate([
            'presensi' => 'required|array',
            'presensi.*' => 'required|in:hadir,sakit,izin,alpa',
        ]);

        $mahasiswaTerdaftar = $this->presensiService->getMahasiswaByKelas($kelas->id)
            ->pluck('id')
            ->map(fn ($mahasiswaId) => (string) $mahasiswaId)
            ->all();
        $mahasiswaYangDikirim = array_map('strval', array_keys($validated['presensi']));

        abort_if(
            array_diff($mahasiswaYangDikirim, $mahasiswaTerdaftar) !== [],
            403,
            'Mahasiswa tidak terdaftar di kelas ini'
        );

        $this->presensiService->recordPresensi($pertemuan->id, $validated['presensi']);

        return redirect()->route('dosen.presensi.kelas', $kelas)
            ->with('success', 'Presensi berhasil disimpan');
    }

    private function authorizeKelas(Kelas $kelas): void
    {
        abort_unless(
            $this->accessibleKelasQuery()->whereKey($kelas->id)->exists(),
            403,
            'Anda tidak memiliki akses ke kelas ini'
        );
    }

    private function accessibleKelasQuery(): Builder
    {
        $user = Auth::user();

        if ($user->role === 'admin_fakultas') {
            abort_unless($user->fakultas_id, 403, 'Admin fakultas belum memiliki fakultas');

            return Kelas::query()->whereHas(
                'dosen.prodi',
                fn ($query) => $query->where('fakultas_id', $user->fakultas_id)
            );
        }

        $dosen = $user->dosen;

        if (! $dosen) {
            abort(403, 'Unauthorized');
        }

        return Kelas::query()->where('dosen_id', $dosen->id);
    }
}
