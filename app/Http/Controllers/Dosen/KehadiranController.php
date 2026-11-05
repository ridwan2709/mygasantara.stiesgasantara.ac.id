<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ValidatesGpsCoordinates;
use App\Models\JadwalKuliah;
use App\Models\KehadiranDosen;
use App\Models\KehadiranMengajar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KehadiranController extends Controller
{
    use ValidatesGpsCoordinates;

    public function index(Request $request)
    {
        $user = Auth::user();
        $dosen = $user->dosen;
        $isStaffLike = in_array($user->role, ['superadmin', 'bendahara', 'staff'], true);
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        if ($dosen === null && ! in_array($user->role, ['superadmin', 'bendahara', 'staff'], true)) {
            abort(403, 'Anda tidak memiliki akses ke halaman kehadiran.');
        }

        $jadwalHariIni = collect();
        $kehadiranHariIni = [];
        $stats = [];
        $riwayat = collect();

        if ($dosen) {
            $today = now();
            $dayName = $today->locale('id')->dayName;
            $dayMap = ['Minggu' => 0, 'Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6];
            $todayIndex = $dayMap[$dayName] ?? $today->dayOfWeek;

            $jadwalHariIni = JadwalKuliah::whereHas('kelas', fn ($q) => $q->where('dosen_id', $dosen->id))
                ->where('hari', $todayIndex)
                ->with('kelas.mataKuliah')
                ->get();

            $kehadiranHariIni = KehadiranDosen::where('dosen_id', $dosen->id)
                ->whereDate('tanggal', $today)
                ->pluck('jadwal_kuliah_id')
                ->toArray();

            $stats = KehadiranDosen::where('dosen_id', $dosen->id)
                ->byMonth($year, $month)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $riwayat = KehadiranDosen::where('dosen_id', $dosen->id)
                ->byMonth($year, $month)
                ->with('jadwalKuliah.kelas.mataKuliah')
                ->orderBy('tanggal', 'desc')
                ->get();
        }

        return view('dosen.kehadiran.index', compact('dosen', 'isStaffLike', 'jadwalHariIni', 'kehadiranHariIni', 'stats', 'riwayat', 'month', 'year'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $isStaffLike = in_array($user->role, ['superadmin', 'bendahara', 'staff'], true);
        $dosen = $user->dosen;

        $validated = $request->validate([
            'jadwal_kuliah_id' => ['nullable', 'exists:jadwal_kuliah,id'],
            'jam_masuk' => 'nullable|date_format:H:i',
            'status' => 'required|in:'.implode(',', array_keys(KehadiranDosen::getStatusList())),
            'keterangan' => 'nullable|string',
            'latitude' => 'required_if:status,hadir|nullable|numeric|between:-90,90',
            'longitude' => 'required_if:status,hadir|nullable|numeric|between:-180,180',
        ]);

        $jadwal = null;
        if (! $isStaffLike) {
            $jadwal = JadwalKuliah::with('kelas.mataKuliah')
                ->whereKey($validated['jadwal_kuliah_id'])
                ->whereHas('kelas', fn ($query) => $query->where('dosen_id', $dosen->id))
                ->first();

            if (! $jadwal) {
                abort(403, 'Jadwal ini bukan milik Anda.');
            }
        }

        $coordinates = null;
        if ($validated['status'] === KehadiranDosen::STATUS_HADIR) {
            $coordinates = $this->validatedGpsCoordinates($validated['latitude'], $validated['longitude']);
            if ($coordinates instanceof RedirectResponse) {
                return $coordinates;
            }
        }

        DB::transaction(function () use ($dosen, $jadwal, $validated, $coordinates, $isStaffLike): void {
            $kehadiran = KehadiranDosen::updateOrCreate(
                [
                    'dosen_id' => $isStaffLike ? null : $dosen->id,
                    'jadwal_kuliah_id' => $jadwal?->id,
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

            if ($validated['status'] === KehadiranDosen::STATUS_HADIR && $jadwal) {
                KehadiranMengajar::query()->firstOrCreate(
                    [
                        'dosen_id' => $dosen->id,
                        'mata_kuliah_id' => $jadwal->kelas->mata_kuliah_id,
                        'kelas_id' => $jadwal->kelas_id,
                        'tanggal' => $kehadiran->tanggal,
                        'jam_mulai' => $kehadiran->jam_masuk,
                        'jam_selesai' => $jadwal->jam_selesai->format('H:i:s'),
                        'status_hadir' => KehadiranDosen::STATUS_HADIR,
                        'status_verifikasi' => KehadiranMengajar::STATUS_PENDING,
                    ]
                );
            }
        });

        if ($jadwal) {
            return redirect()->route('dosen.presensi.kelas', $jadwal->kelas_id)->with('success', 'Kehadiran berhasil dicatat');
        }

        return redirect()->route('dashboard')->with('success', 'Kehadiran berhasil dicatat');
    }

    public function checkout(Request $request, KehadiranDosen $kehadiran)
    {
        $dosen = Auth::user()->dosen;
        if (! $dosen || $kehadiran->dosen_id !== $dosen->id) {
            abort(403);
        }

        DB::transaction(function () use ($kehadiran): void {
            $jamKeluar = now()->format('H:i:s');

            $kehadiran->update([
                'jam_keluar' => $jamKeluar,
            ]);

            $kehadiran->kehadiranMengajar()
                ->where('status_verifikasi', KehadiranMengajar::STATUS_PENDING)
                ->update(['jam_selesai' => $jamKeluar]);
        });

        return redirect()->back()->with('success', 'Checkout berhasil');
    }
}
