<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Krs;
use App\Services\AkademikCalculationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class KhsController extends Controller
{
    public function __construct(private AkademikCalculationService $calculationService) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim($validated['search'] ?? '');

        $krsQuery = Krs::query()
            ->with(['mahasiswa.user', 'tahunAkademik'])
            ->where('status', 'approved')
            ->whereHas('mahasiswa', function ($query) use ($search): void {
                if ($search === '') {
                    return;
                }

                $query->where('nim', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"));
            })
            ->orderByDesc('tahun_akademik_id')
            ->orderBy('mahasiswa_id');

        $khsList = $krsQuery->paginate(20)
            ->withQueryString()
            ->through(function (Krs $krs): array {
                $ipsData = $this->calculationService->calculateIPS(
                    $krs->mahasiswa,
                    $krs->tahun_akademik_id,
                );

                return [
                    'krs' => $krs,
                    'mahasiswa' => $krs->mahasiswa,
                    'tahun_akademik' => $krs->tahunAkademik,
                    'ips' => $ipsData['ips'],
                    'total_sks' => $ipsData['total_sks'],
                ];
            });

        return view('staff.khs.index', compact('khsList', 'search'));
    }
}
