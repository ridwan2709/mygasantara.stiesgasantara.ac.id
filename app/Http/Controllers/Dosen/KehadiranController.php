<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\KehadiranDosen;
use App\Models\JadwalKuliah;
use App\Models\PengaturanGpsKehadiran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class KehadiranController extends Controller
{
    public function index(Request $request)
    {
        $dosen = Auth::user()->dosen;
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        // Get jadwal for today
        $today = now();
        $dayName = $today->locale('id')->dayName;
        $dayMap = ['Minggu' => 0, 'Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6];
        $todayIndex = $dayMap[$dayName] ?? $today->dayOfWeek;

        $jadwalHariIni = JadwalKuliah::whereHas('kelas', fn($q) => $q->where('dosen_id', $dosen->id))
            ->where('hari', $todayIndex)
            ->with('kelas.mataKuliah')
            ->get();

        // Kehadiran hari ini
        $kehadiranHariIni = KehadiranDosen::where('dosen_id', $dosen->id)
            ->whereDate('tanggal', $today)
            ->pluck('jadwal_kuliah_id')
            ->toArray();

        // Stats bulan ini
        $stats = KehadiranDosen::where('dosen_id', $dosen->id)
            ->byMonth($year, $month)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Riwayat
        $riwayat = KehadiranDosen::where('dosen_id', $dosen->id)
            ->byMonth($year, $month)
            ->with('jadwalKuliah.kelas.mataKuliah')
            ->orderBy('tanggal', 'desc')
            ->get();

        return view('dosen.kehadiran.index', compact('dosen', 'jadwalHariIni', 'kehadiranHariIni', 'stats', 'riwayat', 'month', 'year'));
    }

    public function store(Request $request)
    {
        $dosen = Auth::user()->dosen;

        $validated = $request->validate([
            'jadwal_kuliah_id' => 'required|exists:jadwal_kuliah,id',
            'jam_masuk' => 'nullable|date_format:H:i',
            'status' => 'required|in:' . implode(',', array_keys(KehadiranDosen::getStatusList())),
            'keterangan' => 'nullable|string',
            'latitude' => 'required_if:status,hadir|nullable|numeric|between:-90,90',
            'longitude' => 'required_if:status,hadir|nullable|numeric|between:-180,180',
        ]);

        $jadwalMilikDosen = JadwalKuliah::where('id', $validated['jadwal_kuliah_id'])
            ->whereHas('kelas', fn ($query) => $query->where('dosen_id', $dosen->id))
            ->exists();

        if (! $jadwalMilikDosen) {
            abort(403, 'Jadwal ini bukan milik Anda.');
        }

        $coordinates = null;
        if ($validated['status'] === KehadiranDosen::STATUS_HADIR) {
            $coordinates = $this->validatedGpsCoordinates($validated['latitude'], $validated['longitude']);
            if ($coordinates instanceof \Illuminate\Http\RedirectResponse) {
                return $coordinates;
            }
        }

        KehadiranDosen::updateOrCreate(
            [
                'dosen_id' => $dosen->id,
                'jadwal_kuliah_id' => $validated['jadwal_kuliah_id'],
                'tanggal' => now()->toDateString(),
            ],
            [
                'jam_masuk' => $validated['jam_masuk'] ?? now()->format('H:i'),
                'status' => $validated['status'],
                'keterangan' => $validated['keterangan'] ?? null,
                'latitude_masuk' => $coordinates['latitude'] ?? null,
                'longitude_masuk' => $coordinates['longitude'] ?? null,
            ]
        );

        return redirect()->back()->with('success', 'Kehadiran berhasil dicatat');
    }

    public function checkout(Request $request, KehadiranDosen $kehadiran)
    {
        $dosen = Auth::user()->dosen;
        if ($kehadiran->dosen_id !== $dosen->id) abort(403);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $coordinates = $this->validatedGpsCoordinates($validated['latitude'], $validated['longitude']);
        if ($coordinates instanceof \Illuminate\Http\RedirectResponse) {
            return $coordinates;
        }

        $kehadiran->update([
            'jam_keluar' => now()->format('H:i:s'),
            'latitude_keluar' => $coordinates['latitude'],
            'longitude_keluar' => $coordinates['longitude'],
        ]);

        return redirect()->back()->with('success', 'Checkout berhasil');
    }

    /**
     * @return array{latitude: float, longitude: float}|\Illuminate\Http\RedirectResponse
     */
    private function validatedGpsCoordinates(float|string $latitude, float|string $longitude): array|\Illuminate\Http\RedirectResponse
    {
        $setting = PengaturanGpsKehadiran::query()->first();
        if (! $setting) {
            return redirect()->back()->withErrors([
                'gps' => 'Pengaturan lokasi presensi belum tersedia. Hubungi administrator.',
            ])->withInput();
        }

        $coordinates = [
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
        ];

        if (! $setting->contains($coordinates['latitude'], $coordinates['longitude'])) {
            return redirect()->back()->withErrors([
                'gps' => 'Lokasi Anda berada di luar radius presensi kampus.',
            ])->withInput();
        }

        return $coordinates;
    }
}
