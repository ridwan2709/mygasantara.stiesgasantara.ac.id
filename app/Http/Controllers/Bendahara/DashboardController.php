<?php

namespace App\Http\Controllers\Bendahara;

use App\Http\Controllers\Controller;
use App\Models\Dosen;
use App\Models\Honorarium;
use App\Models\KehadiranMengajar;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $currentMonth = now();
        $months = collect(range(5, 0))
            ->map(fn (int $monthsAgo): Carbon => now()->startOfMonth()->subMonths($monthsAgo));

        $honorariumByMonth = Honorarium::query()
            ->where(function (Builder $query) use ($months): void {
                foreach ($months as $month) {
                    $query->orWhere(function (Builder $monthQuery) use ($month): void {
                        $monthQuery->where('tahun', $month->year)
                            ->where('bulan', $month->month);
                    });
                }
            })
            ->selectRaw('tahun, bulan, status_pembayaran, SUM(total_penerimaan) as total, COUNT(*) as jumlah')
            ->groupBy('tahun', 'bulan', 'status_pembayaran')
            ->get()
            ->groupBy(fn (Honorarium $honorarium): string => sprintf('%04d-%02d', $honorarium->tahun, $honorarium->bulan));

        $attendanceStatuses = KehadiranMengajar::query()
            ->whereBetween('tanggal', [
                $months->first()->toDateString(),
                $currentMonth->toDateString(),
            ])
            ->selectRaw('status_verifikasi, COUNT(*) as jumlah')
            ->groupBy('status_verifikasi')
            ->pluck('jumlah', 'status_verifikasi');

        $monthKeys = $months->map(fn (Carbon $month): string => $month->format('Y-m'));
        $monthLabels = $months->map(fn (Carbon $month): string => $month->locale('id')->translatedFormat('M Y'));
        $paidByMonth = $monthKeys->map(fn (string $key): float => (float) ($honorariumByMonth->get($key, collect())
            ->firstWhere('status_pembayaran', Honorarium::STATUS_PAID)?->total ?? 0));
        $draftByMonth = $monthKeys->map(fn (string $key): float => (float) ($honorariumByMonth->get($key, collect())
            ->firstWhere('status_pembayaran', Honorarium::STATUS_DRAFT)?->total ?? 0));

        $monthHonorarium = Honorarium::query()
            ->where('tahun', $currentMonth->year)
            ->where('bulan', $currentMonth->month);

        $paidAmount = (clone $monthHonorarium)
            ->where('status_pembayaran', Honorarium::STATUS_PAID)
            ->sum('total_penerimaan');

        return view('bendahara.dashboard', [
            'dosenCount' => Dosen::query()->count(),
            'attendanceCount' => KehadiranMengajar::query()
                ->whereYear('tanggal', $currentMonth->year)
                ->whereMonth('tanggal', $currentMonth->month)
                ->count(),
            'paidAmount' => $paidAmount,
            'expenseAmount' => $paidAmount,
            'draftCount' => (clone $monthHonorarium)
                ->where('status_pembayaran', Honorarium::STATUS_DRAFT)
                ->count(),
            'paymentChart' => [
                'labels' => $monthLabels->values(),
                'paid' => $paidByMonth->values(),
                'draft' => $draftByMonth->values(),
            ],
            'attendanceChart' => [
                'labels' => ['Menunggu', 'Disetujui', 'Ditolak'],
                'values' => [
                    (int) ($attendanceStatuses->get(KehadiranMengajar::STATUS_PENDING) ?? 0),
                    (int) ($attendanceStatuses->get(KehadiranMengajar::STATUS_APPROVED) ?? 0),
                    (int) ($attendanceStatuses->get(KehadiranMengajar::STATUS_REJECTED) ?? 0),
                ],
            ],
        ]);
    }
}
