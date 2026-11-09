<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Dosen;
use App\Models\JadwalKuliah;
use App\Models\KehadiranDosen;
use App\Models\KehadiranMengajar;
use App\Models\PengaturanGpsKehadiran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KehadiranController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isAdminFakultas = $user->role === 'admin_fakultas' || $user->isAdminProdi();
        $canManageAttendance = $this->canManageAttendance($user->role);
        $isStaffLike = in_array($user->role, ['superadmin', 'bendahara', 'staff'], true);

        $dosenList = $canManageAttendance
            ? $this->accessibleDosenQuery()->with('user')->orderBy('id')->get()
            : collect();

        if ($canManageAttendance) {
            $selectedDosenId = $request->input('dosen_id', $dosenList->first()?->id);
            $dosen = $selectedDosenId
                ? $this->accessibleDosenQuery()->with('user')->find($selectedDosenId)
                : null;

            abort_if($selectedDosenId && ! $dosen, 403, 'Dosen tidak termasuk cakupan akses Anda.');
        } else {
            $dosen = $user->dosen;
            abort_unless($dosen, 403, 'Unauthorized');
            $selectedDosenId = $dosen->id;
        }

        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $today = now();
        $dayName = $today->locale('id')->dayName;
        $dayMap = ['Minggu' => 0, 'Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6];
        $todayIndex = $dayMap[$dayName] ?? $today->dayOfWeek;

        $jadwalHariIni = $dosen
            ? JadwalKuliah::whereHas('kelas', fn ($query) => $query->where('dosen_id', $dosen->id))
                ->where('hari', $todayIndex)
                ->with('kelas.mataKuliah')
                ->get()
            : collect();

        $kehadiranHariIni = KehadiranDosen::where('dosen_id', $dosen?->id)
            ->whereDate('tanggal', $today)
            ->pluck('jadwal_kuliah_id')
            ->toArray();

        $stats = KehadiranDosen::where('dosen_id', $dosen?->id)
            ->byMonth($year, $month)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $riwayat = KehadiranDosen::where('dosen_id', $dosen?->id)
            ->byMonth($year, $month)
            ->with(['jadwalKuliah.kelas.mataKuliah', 'dicatatOleh'])
            ->orderBy('tanggal', 'desc')
            ->get();

        return view('dosen.kehadiran.index', compact(
            'dosen',
            'dosenList',
            'selectedDosenId',
            'isAdminFakultas',
            'isStaffLike',
            'canManageAttendance',
            'jadwalHariIni',
            'kehadiranHariIni',
            'stats',
            'riwayat',
            'month',
            'year'
        ));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $canManageAttendance = $this->canManageAttendance($user->role);
        $validated = $request->validate([
            'jadwal_kuliah_id' => 'required|exists:jadwal_kuliah,id',
            'dosen_id' => $canManageAttendance ? 'required|integer|exists:dosen,id' : 'prohibited',
            'jam_masuk' => 'nullable|date_format:H:i',
            'status' => 'required|in:'.implode(',', array_keys(KehadiranDosen::getStatusList())),
            'keterangan' => 'nullable|string',
            'latitude' => 'required_if:status,hadir|nullable|numeric|between:-90,90',
            'longitude' => 'required_if:status,hadir|nullable|numeric|between:-180,180',
        ]);


        if ($canManageAttendance) {
            $dosen = $this->accessibleDosenQuery()->find($validated['dosen_id']);
            abort_unless($dosen, 403, 'Dosen tidak termasuk cakupan akses Anda.');
        } else {
            $dosen = $user->dosen;
            abort_unless($dosen, 403, 'Unauthorized');
        }

        $jadwal = JadwalKuliah::with('kelas.mataKuliah')
            ->whereKey($validated['jadwal_kuliah_id'])
            ->whereHas('kelas', fn ($query) => $query->where('dosen_id', $dosen->id))
            ->first();

        if (! $jadwal) {
            abort(403, 'Jadwal ini bukan milik dosen yang bersangkutan.');
        }

        $coordinates = null;
        if ($validated['status'] === KehadiranDosen::STATUS_HADIR) {
            $coordinates = $this->validatedGpsCoordinates($validated['latitude'], $validated['longitude']);
            if ($coordinates instanceof RedirectResponse) {
                return $coordinates;
            }
        }

        DB::transaction(function () use ($dosen, $jadwal, $validated, $coordinates, $user): void {
            $kehadiran = KehadiranDosen::updateOrCreate(
                [
                    'dosen_id' => $dosen->id,
                    'jadwal_kuliah_id' => $jadwal->id,
                    'tanggal' => now()->toDateString(),
                ],
                [
                    'jam_masuk' => $validated['jam_masuk'] ?? now()->format('H:i'),
                    'status' => $validated['status'],
                    'keterangan' => $validated['keterangan'] ?? null,
                    'latitude_masuk' => $coordinates['latitude'] ?? null,
                    'longitude_masuk' => $coordinates['longitude'] ?? null,
                    'dicatat_oleh' => $user->id,
                ]
            );

            if ($validated['status'] === KehadiranDosen::STATUS_HADIR) {
                KehadiranMengajar::query()->updateOrCreate(
                    ['kehadiran_dosen_id' => $kehadiran->id],
                    [
                        'dosen_id' => $dosen->id,
                        'mata_kuliah_id' => $jadwal->kelas->mata_kuliah_id,
                        'kelas_id' => $jadwal->kelas_id,
                        'tanggal' => $kehadiran->tanggal,
                        'jam_mulai' => $kehadiran->jam_masuk,
                        'jam_selesai' => $jadwal->jam_selesai->format('H:i:s'),
                        'status_hadir' => KehadiranDosen::STATUS_HADIR,
                        'status_verifikasi' => KehadiranMengajar::STATUS_PENDING,
                        'keterangan' => $validated['keterangan'] ?? null,
                    ]
                );
            } else {
                $kehadiran->kehadiranMengajar()
                    ->where('status_verifikasi', KehadiranMengajar::STATUS_PENDING)
                    ->delete();
            }
        });

        if (! $canManageAttendance) {
            return redirect()->route('dosen.presensi.kelas', $jadwal->kelas_id)
                ->with('success', 'Kehadiran berhasil dicatat');
        }

        return redirect()->back()->with('success', 'Kehadiran berhasil dicatat');
    }

    public function checkout(Request $request, KehadiranDosen $kehadiran)
    {
        $user = Auth::user();
        $this->authorizeKehadiran($kehadiran);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $coordinates = $this->validatedGpsCoordinates($validated['latitude'], $validated['longitude']);
        if ($coordinates instanceof RedirectResponse) {
            return $coordinates;
        }

        DB::transaction(function () use ($kehadiran, $coordinates, $user): void {
            $jamKeluar = now()->format('H:i:s');

            $kehadiran->update([
                'jam_keluar' => $jamKeluar,
                'latitude_keluar' => $coordinates['latitude'],
                'longitude_keluar' => $coordinates['longitude'],
                'dicatat_oleh' => $user->id,
            ]);

            $kehadiran->kehadiranMengajar()
                ->where('status_verifikasi', KehadiranMengajar::STATUS_PENDING)
                ->update(['jam_selesai' => $jamKeluar]);
        });

        return redirect()->back()->with('success', 'Checkout berhasil');
    }

    private function accessibleDosenQuery(): Builder
    {
        $user = Auth::user();

        if ($user->isAdminProdi()) {
            abort_unless($user->prodi_id, 403, 'Admin prodi belum memiliki program studi');

            return Dosen::query()->whereHas(
                'prodi',
                fn ($query) => $query->whereKey($user->prodi_id)
            );
        }

        if ($user->role === 'admin_fakultas') {
            abort_unless($user->fakultas_id, 403, 'Admin fakultas belum memiliki fakultas');

            return Dosen::query()->whereHas(
                'prodi',
                fn ($query) => $query->where('fakultas_id', $user->fakultas_id)
            );
        }

        abort_unless(in_array($user->role, ['superadmin', 'bendahara', 'staff'], true), 403);

        return Dosen::query();
    }

    private function authorizeKehadiran(KehadiranDosen $kehadiran): void
    {
        $user = Auth::user();

        if ($this->canManageAttendance($user->role)) {
            abort_unless(
                $this->accessibleDosenQuery()->whereKey($kehadiran->dosen_id)->exists(),
                403,
                'Anda tidak memiliki akses ke kehadiran ini.'
            );

            return;
        }

        $dosenId = $user->dosen?->id;

        abort_unless(
            $dosenId && $kehadiran->dosen_id === $dosenId,
            403,
            'Anda tidak memiliki akses ke kehadiran ini.'
        );
    }

    private function canManageAttendance(string $role): bool
    {
        return in_array($role, ['admin_fakultas', 'superadmin', 'bendahara', 'staff'], true)
            || Auth::user()->isAdminProdi();
    }

    /**
     * @return array{latitude: float, longitude: float}|RedirectResponse
     */
    private function validatedGpsCoordinates(float|string $latitude, float|string $longitude): array|RedirectResponse
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