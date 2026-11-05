<?php

namespace App\Http\Controllers\Admin;

use App\Exports\HonorariumExport;
use App\Http\Controllers\Controller;
use App\Models\Dosen;
use App\Models\Honorarium;
use App\Models\InsentifManual;
use App\Models\KehadiranDosen;
use App\Models\KehadiranMengajar;
use App\Models\Kelas;
use App\Models\MataKuliah;
use App\Models\TarifHonorPegawai;
use App\Models\TarifHonorarium;
use App\Models\User;
use App\Services\HonorariumService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class HonorariumController extends Controller
{
    private const PEGAWAI_ROLES = ['superadmin', 'bendahara', 'staff'];

    public function __construct(private HonorariumService $honorariumService) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'between:2000,2100'],
        ]);
        $bulan = (int) ($validated['bulan'] ?? now()->month);
        $tahun = (int) ($validated['tahun'] ?? now()->year);
        $dosenList = $this->scopedDosenQuery()->with('user')->orderBy('nidn')->get();
        $kelasQuery = Kelas::with(['dosen.user', 'mataKuliah']);
        if ($request->user()->role === 'admin_prodi') {
            $kelasQuery->whereHas('dosen', fn (Builder $query) => $query->where('prodi_id', $request->user()->prodi_id))
                ->whereHas('mataKuliah', fn (Builder $query) => $query->where('prodi_id', $request->user()->prodi_id));
        }
        $kelasList = $kelasQuery->orderBy('nama_kelas')->get();
        $mataKuliahList = MataKuliah::query()
            ->whereIn('id', $kelasList->pluck('mata_kuliah_id'))
            ->orderBy('nama_mk')
            ->get();
        $honorariumList = Honorarium::with('dosen.user')
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->whereIn('dosen_id', $dosenList->modelKeys())
            ->orderBy('dosen_id')
            ->get();
        $kehadiranList = KehadiranMengajar::with(['dosen.user', 'mataKuliah'])
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->whereIn('dosen_id', $dosenList->modelKeys())
            ->orderByDesc('tanggal')
            ->paginate(20)
            ->withQueryString();
        $insentifList = InsentifManual::with('dosen.user')
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->whereIn('dosen_id', $dosenList->modelKeys())
            ->orderByDesc('tanggal')
            ->get();
        $tarifAktif = TarifHonorarium::query()
            ->whereDate('berlaku_mulai', '<=', today())
            ->where(fn (Builder $query) => $query->whereNull('berlaku_sampai')->orWhereDate('berlaku_sampai', '>=', today()))
            ->latest('berlaku_mulai')
            ->first();
        $honorariumPegawaiList = in_array($request->user()->role, ['superadmin', 'bendahara'], true)
            ? $this->pegawaiAttendanceSummaries($bulan, $tahun)
            : collect();
        $semuaPembayaranPegawai = Honorarium::query()
            ->whereNotNull('nama_pegawai')
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->get();
        $pembayaranPegawai = $semuaPembayaranPegawai->whereNotNull('user_id')->keyBy('user_id');

        $honorariumPegawaiList->transform(function (array $summary) use ($pembayaranPegawai): array {
            $pembayaran = $pembayaranPegawai->get($summary['user_id']);
            $summary['pembayaran'] = $pembayaran;

            if ($pembayaran) {
                $summary['name'] = $pembayaran->nama_pegawai;
                $summary['total_kehadiran'] = $pembayaran->total_kehadiran;
                $summary['total_honor'] = $pembayaran->total_honor_mengajar;
            }

            return $summary;
        });

        foreach ($semuaPembayaranPegawai as $pembayaran) {
            $rowKey = $pembayaran->user_id ?? 'paid-'.$pembayaran->id;

            if ($honorariumPegawaiList->has($rowKey)) {
                continue;
            }

            $honorariumPegawaiList->put($rowKey, [
                'user_id' => $pembayaran->user_id,
                'name' => $pembayaran->nama_pegawai,
                'total_kehadiran' => $pembayaran->total_kehadiran,
                'total_honor' => $pembayaran->total_honor_mengajar,
                'tarif_belum_ada' => 0,
                'pembayaran' => $pembayaran,
            ]);
        }

        $honorariumPegawaiList = $honorariumPegawaiList->sortBy('name')->values();

        return response()->view('admin.keuangan.index', compact(
            'bulan',
            'tahun',
            'dosenList',
            'mataKuliahList',
            'kelasList',
            'honorariumList',
            'kehadiranList',
            'insentifList',
            'tarifAktif',
            'honorariumPegawaiList',
        ));
    }

    public function markPegawaiPaid(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'between:2000,2100'],
        ]);
        $bulan = (int) $validated['bulan'];
        $tahun = (int) $validated['tahun'];

        DB::transaction(function () use ($request, $user, $bulan, $tahun): void {
            $pegawai = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($pegawai->role, self::PEGAWAI_ROLES, true), 403);

            $pembayaran = Honorarium::query()
                ->where('user_id', $pegawai->id)
                ->where('bulan', $bulan)
                ->where('tahun', $tahun)
                ->lockForUpdate()
                ->first();

            abort_if($pembayaran?->status_pembayaran === Honorarium::STATUS_PAID, 409);

            $summary = $this->pegawaiAttendanceSummaries($bulan, $tahun, $pegawai->id)->first();

            if (! $summary || $summary['total_kehadiran'] === 0) {
                throw ValidationException::withMessages([
                    'pegawai' => 'Belum ada absen masuk dan keluar berstatus hadir untuk pegawai ini pada periode tersebut.',
                ]);
            }

            if ($summary['tarif_belum_ada'] > 0) {
                throw ValidationException::withMessages([
                    'pegawai' => 'Tarif pegawai belum tersedia untuk satu atau lebih tanggal kehadiran.',
                ]);
            }

            Honorarium::query()->create([
                'user_id' => $pegawai->id,
                'nama_pegawai' => $pegawai->name,
                'dosen_id' => null,
                'bulan' => $bulan,
                'tahun' => $tahun,
                'total_kehadiran' => $summary['total_kehadiran'],
                'total_honor_mengajar' => $summary['total_honor'],
                'total_insentif_manual' => 0,
                'total_penerimaan' => $summary['total_honor'],
                'status_pembayaran' => Honorarium::STATUS_PAID,
                'tanggal_bayar' => today(),
                'direkap_pada' => now(),
                'dibayar_oleh' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Honor pegawai ditandai sudah dibayar.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{
     *     user_id: int,
     *     name: string,
     *     total_kehadiran: int,
     *     total_honor: string,
     *     tarif_belum_ada: int
     * }>
     */
    private function pegawaiAttendanceSummaries(int $bulan, int $tahun, ?int $userId = null): \Illuminate\Support\Collection
    {
        $periodStart = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $absensi = KehadiranDosen::query()
            ->with('user:id,name,role')
            ->whereNotNull('user_id')
            ->whereNull('dosen_id')
            ->where('status', KehadiranDosen::STATUS_HADIR)
            ->whereNotNull('jam_keluar')
            ->whereBetween('tanggal', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->whereHas('user', fn (Builder $query) => $query->whereIn('role', self::PEGAWAI_ROLES))
            ->when($userId, fn (Builder $query) => $query->where('user_id', $userId))
            ->orderBy('tanggal')
            ->get();

        if ($absensi->isEmpty()) {
            return collect();
        }

        $tarifByUser = TarifHonorPegawai::query()
            ->whereIn('user_id', $absensi->pluck('user_id')->unique())
            ->whereDate('berlaku_mulai', '<=', $periodEnd->toDateString())
            ->where(fn (Builder $query) => $query->whereNull('berlaku_sampai')->orWhereDate('berlaku_sampai', '>=', $periodStart->toDateString()))
            ->orderByDesc('berlaku_mulai')
            ->get()
            ->groupBy('user_id');

        return $absensi->groupBy('user_id')->map(function ($attendances, int|string $id) use ($tarifByUser): array {
            $user = $attendances->first()->user;
            $totalHonorCents = 0;
            $tarifBelumAda = 0;

            foreach ($attendances as $attendance) {
                $tarif = $tarifByUser->get($id, collect())->first(
                    fn (TarifHonorPegawai $tarif): bool => $tarif->berlaku_mulai->lte($attendance->tanggal)
                        && ($tarif->berlaku_sampai === null || $tarif->berlaku_sampai->gte($attendance->tanggal)),
                );

                if (! $tarif) {
                    $tarifBelumAda++;

                    continue;
                }

                $totalHonorCents += (int) round((float) $tarif->nominal * 100);
            }

            return [
                'user_id' => (int) $id,
                'name' => $user->name,
                'total_kehadiran' => $attendances->count(),
                'total_honor' => number_format($totalHonorCents / 100, 2, '.', ''),
                'tarif_belum_ada' => $tarifBelumAda,
            ];
        });
    }

    public function storeKehadiran(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'dosen_id' => ['required', 'exists:dosen,id'],
            'mata_kuliah_id' => ['required', 'exists:mata_kuliah,id'],
            'kelas_id' => ['required', 'exists:kelas,id'],
            'tanggal' => ['required', 'date'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'status_hadir' => ['required', 'in:hadir,izin,sakit,alpa'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ]);
        $dosen = Dosen::with('prodi')->findOrFail($validated['dosen_id']);
        $this->authorizeDosenScope($dosen);

        $validKelas = DB::table('kelas')
            ->where('id', $validated['kelas_id'])
            ->where('dosen_id', $dosen->id)
            ->where('mata_kuliah_id', $validated['mata_kuliah_id'])
            ->exists();

        if (! $validKelas) {
            return back()->withErrors(['kelas_id' => 'Kelas tidak sesuai dengan dosen dan mata kuliah.'])->withInput();
        }

        $this->assertPeriodIsNotPaid($dosen->id, $validated['tanggal']);

        KehadiranMengajar::create([
            ...$validated,
            'status_verifikasi' => KehadiranMengajar::STATUS_PENDING,
        ]);

        return back()->with('success', 'Kehadiran mengajar ditambahkan dan menunggu verifikasi.');
    }

    public function verifyKehadiran(Request $request, KehadiranMengajar $kehadiranMengajar): RedirectResponse
    {
        $validated = $request->validate([
            'status_verifikasi' => ['required', 'in:approved,rejected'],
        ]);
        $kehadiranMengajar->load('dosen.prodi');
        $this->authorizeDosenScope($kehadiranMengajar->dosen);
        abort_unless($kehadiranMengajar->status_verifikasi === KehadiranMengajar::STATUS_PENDING, 409);
        $this->assertPeriodIsNotPaid($kehadiranMengajar->dosen_id, $kehadiranMengajar->tanggal);

        DB::transaction(function () use ($request, $validated, $kehadiranMengajar): void {
            $kehadiranMengajar->update([
                'status_verifikasi' => $validated['status_verifikasi'],
                'diverifikasi_oleh' => $request->user()->id,
                'diverifikasi_pada' => now(),
            ]);

            $this->honorariumService->recalculate(
                $kehadiranMengajar->dosen_id,
                $kehadiranMengajar->tanggal->month,
                $kehadiranMengajar->tanggal->year,
            );
        });

        return back()->with('success', 'Status verifikasi disimpan dan rekap honorarium diperbarui.');
    }

    public function storeInsentif(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'dosen_id' => ['required', 'exists:dosen,id'],
            'jenis_insentif' => ['required', 'string', 'max:150'],
            'nominal' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'tanggal' => ['required', 'date'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ]);
        $dosen = Dosen::with('prodi')->findOrFail($validated['dosen_id']);
        $this->authorizeDosenScope($dosen);
        $this->assertPeriodIsNotPaid($dosen->id, $validated['tanggal']);

        InsentifManual::create([
            ...$validated,
            'input_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Insentif manual berhasil ditambahkan.');
    }

    public function storeTarif(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $validated = $request->validate([
            'basis_tarif' => ['required', 'in:per_sks,per_kehadiran'],
            'nominal' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'berlaku_mulai' => ['required', 'date'],
        ]);

        if (TarifHonorarium::whereDate('berlaku_mulai', '>=', $validated['berlaku_mulai'])->exists()) {
            throw ValidationException::withMessages([
                'berlaku_mulai' => 'Tanggal mulai tarif baru harus lebih besar dari tanggal tarif yang sudah terdaftar.',
            ]);
        }

        DB::transaction(function () use ($validated, $request): void {
            $berlakuMulai = $validated['berlaku_mulai'];
            $tarifTerdahulu = TarifHonorarium::query()
                ->whereNull('berlaku_sampai')
                ->whereDate('berlaku_mulai', '<', $berlakuMulai)
                ->latest('berlaku_mulai')
                ->first();

            if ($tarifTerdahulu) {
                $tarifTerdahulu->update([
                    'berlaku_sampai' => Carbon::parse($berlakuMulai)->subDay()->toDateString(),
                ]);
            }

            TarifHonorarium::create([
                ...$validated,
                'dibuat_oleh' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Tarif honorarium berhasil disimpan.');
    }

    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'between:2000,2100'],
            'dosen_id' => ['nullable', 'exists:dosen,id'],
        ]);
        $bulan = (int) $validated['bulan'];
        $tahun = (int) $validated['tahun'];
        $dosenQuery = $this->scopedDosenQuery();

        if (! empty($validated['dosen_id'])) {
            $dosenQuery->whereKey($validated['dosen_id']);
        }

        $dosenList = $dosenQuery->get();
        if (! empty($validated['dosen_id']) && $dosenList->isEmpty()) {
            abort(404);
        }

        DB::transaction(function () use ($dosenList, $bulan, $tahun): void {
            foreach ($dosenList as $dosen) {
                $this->honorariumService->recalculate($dosen->id, $bulan, $tahun);
            }
        });

        return back()->with('success', 'Rekap honorarium berhasil dibuat untuk '.$dosenList->count().' dosen.');
    }

    public function markPaid(Honorarium $honorarium): RedirectResponse
    {
        abort_unless($honorarium->dosen_id !== null, 404);
        $honorarium->load('dosen.prodi');
        $this->authorizeDosenScope($honorarium->dosen);
        abort_unless($honorarium->status_pembayaran === Honorarium::STATUS_DRAFT, 409);
        $honorarium->update([
            'status_pembayaran' => Honorarium::STATUS_PAID,
            'tanggal_bayar' => today(),
        ]);

        return back()->with('success', 'Honorarium ditandai sudah dibayar.');
    }

    public function exportPdf(Request $request): Response
    {
        [$bulan, $tahun, $honorariumList] = $this->exportData($request);
        $honorariumPegawaiList = in_array($request->user()->role, ['superadmin', 'bendahara'], true)
            ? $this->pegawaiAttendanceSummaries($bulan, $tahun)
            : collect();
        $semuaPembayaranPegawai = Honorarium::query()
            ->whereNotNull('nama_pegawai')
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->get();
        $pembayaranPegawai = $semuaPembayaranPegawai->whereNotNull('user_id')->keyBy('user_id');

        $honorariumPegawaiList->transform(function (array $summary) use ($pembayaranPegawai): array {
            $pembayaran = $pembayaranPegawai->get($summary['user_id']);
            $summary['status_pembayaran'] = $pembayaran?->status_pembayaran ?? Honorarium::STATUS_DRAFT;
            $summary['tanggal_bayar'] = $pembayaran?->tanggal_bayar;
            $summary['status_pembayaran'] = $pembayaran?->status_pembayaran ?? Honorarium::STATUS_DRAFT;
            $summary['tanggal_bayar'] = $pembayaran?->tanggal_bayar;

            if ($pembayaran) {
                $summary['name'] = $pembayaran->nama_pegawai;
                $summary['total_kehadiran'] = $pembayaran->total_kehadiran;
                $summary['total_honor'] = $pembayaran->total_honor_mengajar;
            }

            return $summary;
        });

        foreach ($semuaPembayaranPegawai as $pembayaran) {
            if ($pembayaran->user_id !== null && $honorariumPegawaiList->has($pembayaran->user_id)) {
                continue;
            }

            $honorariumPegawaiList->push([
                'user_id' => $pembayaran->user_id,
                'name' => $pembayaran->nama_pegawai,
                'total_kehadiran' => $pembayaran->total_kehadiran,
                'total_honor' => $pembayaran->total_honor_mengajar,
                'tarif_belum_ada' => 0,
                'status_pembayaran' => $pembayaran->status_pembayaran,
                'tanggal_bayar' => $pembayaran->tanggal_bayar,
            ]);
        }

        return Pdf::loadView('admin.keuangan.pdf', compact('bulan', 'tahun', 'honorariumList', 'honorariumPegawaiList'))
            ->download("honorarium-{$tahun}-".str_pad((string) $bulan, 2, '0', STR_PAD_LEFT).'.pdf');
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        [$bulan, $tahun, $honorariumList] = $this->exportData($request);

        return Excel::download(
            new HonorariumExport($honorariumList),
            "honorarium-{$tahun}-".str_pad((string) $bulan, 2, '0', STR_PAD_LEFT).'.xlsx',
        );
    }

    private function exportData(Request $request): array
    {
        $validated = $request->validate([
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'between:2000,2100'],
        ]);
        $bulan = (int) $validated['bulan'];
        $tahun = (int) $validated['tahun'];
        $honorariumList = Honorarium::with('dosen.user')
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->whereIn('dosen_id', $this->scopedDosenQuery()->select('dosen.id'))
            ->orderBy('dosen_id')
            ->get();

        return [$bulan, $tahun, $honorariumList];
    }

    private function scopedDosenQuery(): Builder
    {
        $query = Dosen::query();

        if (request()->user()->role === 'admin_prodi') {
            $query->where('prodi_id', request()->user()->prodi_id);
        }

        return $query;
    }

    private function authorizeDosenScope(Dosen $dosen): void
    {
        if (request()->user()->role === 'admin_prodi'
            && (int) $dosen->prodi_id !== (int) request()->user()->prodi_id) {
            abort(403);
        }
    }

    private function assertPeriodIsNotPaid(int $dosenId, string $date): void
    {
        $period = Carbon::parse($date);
        $periodIsPaid = Honorarium::query()
            ->where('dosen_id', $dosenId)
            ->where('tahun', $period->year)
            ->where('bulan', $period->month)
            ->where('status_pembayaran', Honorarium::STATUS_PAID)
            ->exists();

        if ($periodIsPaid) {
            throw ValidationException::withMessages([
                'periode' => 'Data honorarium periode ini sudah dibayar dan tidak dapat divalidasi.',
            ]);
        }
    }
}
