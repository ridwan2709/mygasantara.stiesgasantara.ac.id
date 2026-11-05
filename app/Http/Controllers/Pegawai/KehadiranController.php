<?php

namespace App\Http\Controllers\Pegawai;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ValidatesGpsCoordinates;
use App\Models\KehadiranDosen;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KehadiranController extends Controller
{
    use ValidatesGpsCoordinates;

    public function index(): View
    {
        $userId = auth()->id();
        $tanggalHariIni = now()->toDateString();
        $kehadiranHariIni = KehadiranDosen::query()
            ->where('user_id', $userId)
            ->whereDate('tanggal', $tanggalHariIni)
            ->first();

        $riwayat = KehadiranDosen::query()
            ->where('user_id', $userId)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(31)
            ->get();

        return view('pegawai.kehadiran.index', compact('kehadiranHariIni', 'riwayat'));
    }

    public function masuk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $coordinates = $this->validatedGpsCoordinates($validated['latitude'], $validated['longitude']);
        if ($coordinates instanceof RedirectResponse) {
            return $coordinates;
        }

        $userId = auth()->id();
        $now = now();
        $tanggal = $now->toDateString();

        try {
            $kehadiran = KehadiranDosen::query()->firstOrCreate(
                [
                    'user_id' => $userId,
                    'tanggal' => $tanggal,
                ],
                [
                    'dosen_id' => null,
                    'jadwal_kuliah_id' => null,
                    'status' => KehadiranDosen::STATUS_HADIR,
                    'jam_masuk' => $now->format('H:i:s'),
                    'latitude_masuk' => $coordinates['latitude'],
                    'longitude_masuk' => $coordinates['longitude'],
                    'keterangan' => $validated['keterangan'] ?? null,
                ],
            );
        } catch (QueryException $exception) {
            $alreadyCheckedIn = KehadiranDosen::query()
                ->where('user_id', $userId)
                ->whereDate('tanggal', $tanggal)
                ->exists();

            if (! $alreadyCheckedIn) {
                throw $exception;
            }

            return back()->withErrors(['absen' => 'Anda sudah absen masuk hari ini.'])->withInput();
        }

        if (! $kehadiran->wasRecentlyCreated) {
            return back()->withErrors(['absen' => 'Anda sudah absen masuk hari ini.'])->withInput();
        }

        return redirect()->route('pegawai.kehadiran.index')->with('success', 'Absen masuk berhasil dicatat.');
    }

    public function keluar(): RedirectResponse
    {
        $now = now();
        $kehadiran = KehadiranDosen::query()
            ->where('user_id', auth()->id())
            ->whereDate('tanggal', $now->toDateString())
            ->first();

        if (! $kehadiran) {
            return back()->withErrors(['absen' => 'Anda belum absen masuk hari ini.']);
        }

        if ($kehadiran->jam_keluar !== null) {
            return back()->withErrors(['absen' => 'Anda sudah absen keluar hari ini.']);
        }

        $updated = KehadiranDosen::query()
            ->whereKey($kehadiran->id)
            ->whereNull('jam_keluar')
            ->update(['jam_keluar' => $now->format('H:i:s')]);

        if (! $updated) {
            return back()->withErrors(['absen' => 'Anda sudah absen keluar hari ini.']);
        }

        return redirect()->route('pegawai.kehadiran.index')->with('success', 'Absen keluar berhasil dicatat.');
    }
}
