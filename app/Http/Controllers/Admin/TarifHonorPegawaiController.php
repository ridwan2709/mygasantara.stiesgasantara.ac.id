<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

    public function index(): View
    {
        $today = today();
        $pegawaiList = User::query()
            ->whereIn('role', self::PEGAWAI_ROLES)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $allTarif = TarifHonorPegawai::query()
            ->with('user:id,name,email')
            ->orderBy('user_id')
            ->orderByDesc('berlaku_mulai')
            ->get();

        $isActiveToday = static fn (TarifHonorPegawai $tarif): bool => $tarif->berlaku_mulai->lte($today)
            && ($tarif->berlaku_sampai === null || $tarif->berlaku_sampai->gte($today));

        $tarifAktif = $allTarif->filter($isActiveToday)->values();
        $tarifRiwayat = $allTarif->reject($isActiveToday)->values();

        return view('admin.keuangan.tarif-pegawai', compact('pegawaiList', 'tarifAktif', 'tarifRiwayat'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', self::PEGAWAI_ROLES)),
            ],
            'nominal' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'berlaku_mulai' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($validated, $request): void {
            $user = User::query()
                ->whereKey($validated['user_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($user->role, self::PEGAWAI_ROLES, true)) {
                throw ValidationException::withMessages([
                    'user_id' => 'User yang dipilih bukan pegawai yang dapat diatur tarifnya.',
                ]);
            }

            $hasLaterOrSameTarif = TarifHonorPegawai::query()
                ->where('user_id', $user->id)
                ->whereDate('berlaku_mulai', '>=', $validated['berlaku_mulai'])
                ->exists();

            if ($hasLaterOrSameTarif) {
                throw ValidationException::withMessages([
                    'berlaku_mulai' => 'Tanggal mulai tarif baru harus lebih besar dari tanggal tarif pegawai yang sudah terdaftar.',
                ]);
            }

            $tarifTerdahulu = TarifHonorPegawai::query()
                ->where('user_id', $user->id)
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
                ...$validated,
                'dibuat_oleh' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Tarif honor pegawai berhasil disimpan.');
    }
}
