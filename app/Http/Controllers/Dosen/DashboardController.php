<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\KehadiranDosen;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Nilai;
use App\Models\Pertemuan;
use App\Models\Presensi;
use App\Models\TahunAkademik;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $dosen = $user->dosen;
        
        if (!$dosen) {
            abort(403, 'Unauthorized');
        }

        $activeTA = TahunAkademik::where('is_active', true)->first();

        // Get kelas yang diampu (filter by active tahun akademik if available)
        $kelasQuery = Kelas::where('dosen_id', $dosen->id)
            ->with(['mataKuliah', 'krsDetail.krs.mahasiswa', 'jadwal']);
        
        if ($activeTA) {
            $kelasQuery->where(function($q) use ($activeTA) {
                $q->where('tahun_akademik_id', $activeTA->id)
                  ->orWhereNull('tahun_akademik_id'); // Include legacy kelas without TA
            });
        }
        
        $kelasList = $kelasQuery->get();

        // Stats
        $totalKelas = $kelasList->count();
        $totalMahasiswa = $kelasList->sum(fn($k) => $k->krsDetail->count());
        
        // Get total nilai yang sudah diinput
        $nilaiDiinput = Nilai::whereIn('kelas_id', $kelasList->pluck('id'))
            ->whereNotNull('nilai_angka')
            ->count();
        $totalNilai = $kelasList->sum(fn($k) => $k->krsDetail->count());
        $persentaseNilai = $totalNilai > 0 ? round(($nilaiDiinput / $totalNilai) * 100) : 0;

        // Presensi stats
        $pertemuanList = Pertemuan::whereHas('jadwalKuliah', fn($q) => $q->whereIn('kelas_id', $kelasList->pluck('id')))->get();
        $totalPertemuan = $pertemuanList->count();
        
        $presensiStats = Presensi::whereIn('pertemuan_id', $pertemuanList->pluck('id'))
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Mahasiswa bimbingan
        $mahasiswaBimbingan = $dosen->mahasiswaBimbingan()->with('user')->get();
        $pendingKrs = $mahasiswaBimbingan->isNotEmpty() 
            ? Krs::whereIn('mahasiswa_id', $mahasiswaBimbingan->pluck('id'))
                ->where('status', 'pending')
                ->count()
            : 0;

        // Recent activities (recent nilai input)
        $recentNilai = Nilai::whereIn('kelas_id', $kelasList->pluck('id'))
            ->whereNotNull('nilai_angka')
            ->with(['kelas.mataKuliah', 'mahasiswa.user'])
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        // Jadwal hari ini
        $hariMapping = [
            'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
        ];
        $hariIni = $hariMapping[now()->format('l')] ?? now()->format('l');
        $currentTime = now()->format('H:i:s');
        $kehadiranHariIni = KehadiranDosen::query()
            ->where('dosen_id', $dosen->id)
            ->whereDate('tanggal', now())
            ->get()
            ->keyBy('jadwal_kuliah_id');

        $jadwalHariIni = $kelasList->flatMap(function ($kelas) use ($hariIni) {
            return $kelas->jadwal
                ->where('hari', $hariIni)
                ->map(fn ($jadwal) => ['kelas' => $kelas, 'jadwal' => $jadwal]);
        })->sortBy(fn ($item) => $item['jadwal']->jam_mulai->format('H:i:s'))->values();

        $belumCheckout = fn ($item) => ! $kehadiranHariIni->get($item['jadwal']->id)?->jam_keluar;
        $utama = $jadwalHariIni->first(function ($item) use ($belumCheckout, $currentTime) {
            $jadwal = $item['jadwal'];

            return $belumCheckout($item)
                && $jadwal->jam_mulai->format('H:i:s') <= $currentTime
                && $jadwal->jam_selesai->format('H:i:s') >= $currentTime;
        }) ?? $jadwalHariIni->first(function ($item) use ($belumCheckout, $currentTime) {
            return $belumCheckout($item)
                && $item['jadwal']->jam_mulai->format('H:i:s') > $currentTime;
        }) ?? $jadwalHariIni->first($belumCheckout) ?? $jadwalHariIni->last();

        $kelasUtamaHariIni = $utama['kelas'] ?? null;
        $jadwalUtamaHariIni = $utama['jadwal'] ?? null;
        $kehadiranUtama = $jadwalUtamaHariIni
            ? $kehadiranHariIni->get($jadwalUtamaHariIni->id)
            : null;
        $jadwalLainnyaHariIni = $jadwalHariIni
            ->reject(fn ($item) => $item['jadwal']->id === $jadwalUtamaHariIni?->id)
            ->values();

        return view('dosen.dashboard.index', compact(
            'dosen', 'activeTA', 'kelasList', 'totalKelas', 'totalMahasiswa',
            'nilaiDiinput', 'totalNilai', 'persentaseNilai', 'totalPertemuan',
            'presensiStats', 'mahasiswaBimbingan', 'pendingKrs', 'recentNilai',
            'kelasUtamaHariIni', 'jadwalUtamaHariIni', 'kehadiranUtama',
            'jadwalLainnyaHariIni', 'kehadiranHariIni', 'hariIni'
        ));
    }
}
