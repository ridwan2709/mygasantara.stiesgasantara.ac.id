<?php

namespace App\Http\Controllers\Admin;

use App\Exports\HonorariumExport;
use App\Http\Controllers\Controller;
use App\Models\Dosen;
use App\Models\Honorarium;
use App\Models\InsentifManual;
use App\Models\KehadiranMengajar;
use App\Models\Kelas;
use App\Models\MataKuliah;
use App\Models\TarifHonorarium;
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
        if ($request->user()->role === 'admin_fakultas') {
            $kelasQuery->whereHas('dosen.prodi', fn (Builder $query) => $query->where('fakultas_id', $request->user()->fakultas_id));
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
        ));
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

        return Pdf::loadView('admin.keuangan.pdf', compact('bulan', 'tahun', 'honorariumList'))
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

        if (request()->user()->role === 'admin_fakultas') {
            $query->whereHas('prodi', fn (Builder $prodiQuery) => $prodiQuery->where('fakultas_id', request()->user()->fakultas_id));
        }

        return $query;
    }

    private function authorizeDosenScope(Dosen $dosen): void
    {
        if (request()->user()->role === 'admin_fakultas'
            && (int) $dosen->prodi?->fakultas_id !== (int) request()->user()->fakultas_id) {
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
