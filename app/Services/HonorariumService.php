<?php

namespace App\Services;

use App\Models\Honorarium;
use App\Models\InsentifManual;
use App\Models\KehadiranMengajar;
use App\Models\TarifHonorarium;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class HonorariumService
{
    /**
     * Recalculate honorarium totals; this method must be called inside a DB::transaction().
     */
    public function recalculate(int $dosenId, int $bulan, int $tahun): ?Honorarium
    {
        $summary = Honorarium::query()
            ->where('dosen_id', $dosenId)
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->lockForUpdate()
            ->first();

        if ($summary?->status_pembayaran === Honorarium::STATUS_PAID) {
            return null;
        }

        $kehadiranList = KehadiranMengajar::with('mataKuliah')
            ->where('dosen_id', $dosenId)
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->where('status_hadir', 'hadir')
            ->where('status_verifikasi', KehadiranMengajar::STATUS_APPROVED)
            ->get();

        $totalHonorMengajar = 0.0;
        foreach ($kehadiranList as $kehadiran) {
            $tarif = $this->tarifForDate($kehadiran->tanggal->toDateString());
            if (! $tarif) {
                throw ValidationException::withMessages([
                    'tarif' => "Tarif honorarium belum tersedia untuk tanggal {$kehadiran->tanggal->toDateString()}.",
                ]);
            }

            $totalHonorMengajar += $tarif->basis_tarif === 'per_sks'
                ? (float) $tarif->nominal * (int) $kehadiran->mataKuliah->sks
                : (float) $tarif->nominal;
        }

        $totalInsentif = (float) InsentifManual::query()
            ->where('dosen_id', $dosenId)
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->sum('nominal');
        $totals = [
            'total_kehadiran' => $kehadiranList->count(),
            'total_honor_mengajar' => round($totalHonorMengajar, 2),
            'total_insentif_manual' => round($totalInsentif, 2),
            'total_penerimaan' => round($totalHonorMengajar + $totalInsentif, 2),
            'status_pembayaran' => Honorarium::STATUS_DRAFT,
            'tanggal_bayar' => null,
            'direkap_pada' => now(),
        ];

        return Honorarium::updateOrCreate(
            ['dosen_id' => $dosenId, 'bulan' => $bulan, 'tahun' => $tahun],
            $totals,
        );
    }

    public function tarifForDate(string $date): ?TarifHonorarium
    {
        return TarifHonorarium::query()
            ->whereDate('berlaku_mulai', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('berlaku_sampai')->orWhereDate('berlaku_sampai', '>=', $date))
            ->latest('berlaku_mulai')
            ->first();
    }
}
