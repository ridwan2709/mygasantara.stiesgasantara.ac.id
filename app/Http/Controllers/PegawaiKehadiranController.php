<?php

namespace App\Http\Controllers;

use App\Models\KehadiranPegawai;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PegawaiKehadiranController extends Controller
{
    use ValidatesGpsCoordinates;

    public function index(): View
    {
        if (! Schema::hasTable('kehadiran_pegawai')) {
            return view('pegawai-kehadiran', [
                'todayAttendance' => null,
                'history' => collect(),
            ]);
        }

        $today = now()->toDateString();
        $userId = Auth::id();

        $todayAttendance = KehadiranPegawai::query()
            ->where('user_id', $userId)
            ->whereDate('tanggal', $today)
            ->first();

        $history = KehadiranPegawai::query()
            ->where('user_id', $userId)
            ->orderByDesc('tanggal')
            ->limit(31)
            ->get();

        return view('pegawai-kehadiran', [
            'todayAttendance' => $todayAttendance,
            'history' => $history,
        ]);
    }

    public function masuk(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('kehadiran_pegawai')) {
            return redirect()->back()->withErrors([
                'attendance' => 'Tabel kehadiran pegawai belum dibuat. Jalankan migrasi database terlebih dahulu.',
            ]);
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $coordinates = $this->validatedGpsCoordinates((float) $validated['latitude'], (float) $validated['longitude']);

        if ($coordinates instanceof \Illuminate\Http\RedirectResponse) {
            return $coordinates;
        }

        $today = now()->toDateString();
        $userId = Auth::id();

        $existingAttendance = KehadiranPegawai::query()
            ->where('user_id', $userId)
            ->whereDate('tanggal', $today)
            ->first();

        if ($existingAttendance && ! empty($existingAttendance->jam_masuk)) {
            return redirect()->back()->withErrors([
                'attendance' => 'Anda sudah absen masuk hari ini.',
            ]);
        }

        KehadiranPegawai::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'tanggal' => $today,
            ],
            [
                'jam_masuk' => now()->format('H:i:s'),
                'jam_keluar' => $existingAttendance?->jam_keluar,
                'latitude_masuk' => $coordinates['latitude'],
                'longitude_masuk' => $coordinates['longitude'],
                'keterangan' => $validated['keterangan'] ?? null,
            ]
        );

        return redirect()->back()->with('success', 'Absen masuk berhasil dicatat.');
    }

    public function keluar(): RedirectResponse
    {
        if (! Schema::hasTable('kehadiran_pegawai')) {
            return redirect()->back()->withErrors([
                'attendance' => 'Tabel kehadiran pegawai belum dibuat. Jalankan migrasi database terlebih dahulu.',
            ]);
        }

        $today = now()->toDateString();
        $userId = Auth::id();

        $attendance = KehadiranPegawai::query()
            ->where('user_id', $userId)
            ->whereDate('tanggal', $today)
            ->first();

        if (! $attendance || empty($attendance->jam_masuk)) {
            return redirect()->back()->withErrors([
                'attendance' => 'Anda belum absen masuk hari ini.',
            ]);
        }

        if (! empty($attendance->jam_keluar)) {
            return redirect()->back()->withErrors([
                'attendance' => 'Anda sudah absen keluar hari ini.',
            ]);
        }

        $attendance->update([
            'jam_keluar' => now()->format('H:i:s'),
        ]);

        return redirect()->back()->with('success', 'Absen keluar berhasil dicatat.');
    }
}
