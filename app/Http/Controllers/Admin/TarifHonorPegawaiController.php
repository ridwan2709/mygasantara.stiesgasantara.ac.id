<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TarifHonorarium;
use App\Models\TarifHonorPegawai;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TarifHonorPegawaiController extends Controller
{
    private const PEGAWAI_ROLES = ['superadmin', 'bendahara', 'staff'];

    private const ROLE_LABELS = [
        'superadmin' => 'Superadmin',
        'bendahara' => 'Bendahara',
        'staff' => 'Staff',
    ];

    public function index(): View
    {
        $today = today();
        $pegawaiList = User::query()
            ->whereIn('role', self::PEGAWAI_ROLES)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $allTarif = TarifHonorPegawai::query()
            ->with('user:id,name,email')
            ->orderBy('role')
            ->orderBy('user_id')
            ->orderByDesc('berlaku_mulai')
            ->get();

        $isActiveToday = static fn (TarifHonorarium|TarifHonorPegawai $tarif): bool => $tarif->berlaku_mulai->lte($today)
            && ($tarif->berlaku_sampai === null || $tarif->berlaku_sampai->gte($today));

        $tarifAktif = $allTarif->filter($isActiveToday)->values();
        $tarifRiwayat = $allTarif->reject($isActiveToday)->values();
        $allTarifDosen = TarifHonorarium::query()->orderByDesc('berlaku_mulai')->get();
        $tarifDosenAktif = $allTarifDosen->filter($isActiveToday)->values();
        $tarifDosenRiwayat = $allTarifDosen->reject($isActiveToday)->values();

        return view('admin.keuangan.pengaturan-tarif', [
            'pegawaiList' => $pegawaiList,
            'tarifAktif' => $tarifAktif,
            'tarifRiwayat' => $tarifRiwayat,
            'tarifDosenAktif' => $tarifDosenAktif,
            'tarifDosenRiwayat' => $tarifDosenRiwayat,
            'roleLabels' => self::ROLE_LABELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'target_type' => ['required', Rule::in(['role', 'user'])],
            'role' => ['nullable', 'required_if:target_type,role', Rule::in(self::PEGAWAI_ROLES)],
            'user_id' => [
                'nullable',
                'required_if:target_type,user',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', self::PEGAWAI_ROLES)),
            ],
            'nominal' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'berlaku_mulai' => ['required', 'date'],
        ]);

        $scopeColumn = $validated['target_type'] === 'role' ? 'role' : 'user_id';
        $scopeValue = $validated[$scopeColumn];

        DB::transaction(function () use ($validated, $request, $scopeColumn, $scopeValue): void {
            if ($scopeColumn === 'user_id') {
                $user = User::query()->whereKey($scopeValue)->lockForUpdate()->firstOrFail();

                if (! in_array($user->role, self::PEGAWAI_ROLES, true)) {
                    throw ValidationException::withMessages([
                        'user_id' => 'User yang dipilih bukan pegawai yang dapat diatur tarifnya.',
                    ]);
                }
            }

            $hasLaterOrSameTarif = TarifHonorPegawai::query()
                ->where($scopeColumn, $scopeValue)
                ->whereDate('berlaku_mulai', '>=', $validated['berlaku_mulai'])
                ->exists();

            if ($hasLaterOrSameTarif) {
                throw ValidationException::withMessages([
                    'berlaku_mulai' => 'Tanggal mulai tarif baru harus lebih besar dari tanggal tarif untuk pilihan ini yang sudah terdaftar.',
                ]);
            }

            $tarifTerdahulu = TarifHonorPegawai::query()
                ->where($scopeColumn, $scopeValue)
                ->whereNull('berlaku_sampai')
                ->whereDate('berlaku_mulai', '<', $validated['berlaku_mulai'])
                ->latest('berlaku_mulai')
                ->first();

            if ($tarifTerdahulu) {
                $tarifTerdahulu->update([
                    'berlaku_sampai' => Carbon::parse($validated['berlaku_mulai'])->subDay()->toDateString(),
                ]);
            }

            TarifHonorPegawai::query()->create([
                $scopeColumn => $scopeValue,
                'nominal' => $validated['nominal'],
                'berlaku_mulai' => $validated['berlaku_mulai'],
                'dibuat_oleh' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Tarif honor pegawai berhasil disimpan.');
    }
}
