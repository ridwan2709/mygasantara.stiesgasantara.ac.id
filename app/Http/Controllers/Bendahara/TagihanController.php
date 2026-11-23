<?php

namespace App\Http\Controllers\Bendahara;

use App\Http\Controllers\Controller;
use App\Models\Mahasiswa;
use App\Models\Notification;
use App\Models\Prodi;
use App\Models\TagihanMahasiswa;
use App\Services\FonnteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class TagihanController extends Controller
{
    public function __construct(private FonnteService $fonnteService) {}

    public function index(Request $request): View
    {
        $query = TagihanMahasiswa::query()
            ->with(['mahasiswa.user', 'mahasiswa.prodi']);

        if ($search = $request->string('search')->toString()) {
            $query->whereHas('mahasiswa.user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orWhereHas('mahasiswa', fn ($q) => $q->where('nim', 'like', "%{$search}%"));
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($prodiId = $request->integer('prodi_id')) {
            $query->whereHas('mahasiswa', fn ($q) => $q->where('prodi_id', $prodiId));
        }
        if ($angkatan = $request->string('angkatan')->toString()) {
            $query->whereHas('mahasiswa', fn ($q) => $q->where('angkatan', $angkatan));
        }
        if ($rombel = $request->string('rombel')->toString()) {
            $query->whereHas('mahasiswa', fn ($q) => $q->where('rombel', mb_strtoupper($rombel)));
        }

        $tagihanList = $query->latest()->paginate(20)->withQueryString();
        $mahasiswaList = Mahasiswa::with(['user', 'prodi'])->aktif()->orderBy('nim')->get();
        $prodiList = Prodi::orderBy('nama')->get();
        $angkatanList = Mahasiswa::distinct()->whereNotNull('angkatan')->orderByDesc('angkatan')->pluck('angkatan');
        $rombelList = Mahasiswa::distinct()->whereNotNull('rombel')->orderBy('rombel')->pluck('rombel');

        return view('bendahara.tagihan.index', compact(
            'tagihanList',
            'mahasiswaList',
            'prodiList',
            'angkatanList',
            'rombelList',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'target_type' => ['required', 'in:mahasiswa,prodi,angkatan,rombel'],
            'mahasiswa_id' => ['required_if:target_type,mahasiswa', 'nullable', 'exists:mahasiswa,id'],
            'prodi_id' => ['required_if:target_type,prodi', 'nullable', 'exists:prodi,id'],
            'angkatan' => ['required_if:target_type,angkatan', 'nullable', 'string', 'digits:4'],
            'rombel' => ['required_if:target_type,rombel', 'nullable', 'string', 'max:50'],
            'nama' => ['required', 'string', 'max:255'],
            'jumlah' => ['required', 'numeric', 'min:0'],
            'jatuh_tempo' => ['required', 'date'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'send_now' => ['nullable', 'boolean'],
        ]);

        $students = $this->resolveStudents($validated);

        if ($students->isEmpty()) {
            throw ValidationException::withMessages(['target_type' => 'Tidak ada mahasiswa yang sesuai dengan filter.']);
        }

        $created = collect();

        DB::transaction(function () use ($validated, $students, &$created, $request): void {
            foreach ($students as $mahasiswa) {
                $tagihan = TagihanMahasiswa::create([
                    'mahasiswa_id' => $mahasiswa->id,
                    'created_by' => $request->user()->id,
                    'nama' => $validated['nama'],
                    'jumlah' => $validated['jumlah'],
                    'jatuh_tempo' => $validated['jatuh_tempo'],
                    'keterangan' => $validated['keterangan'] ?? null,
                    'status' => TagihanMahasiswa::STATUS_BELUM_BAYAR,
                ]);
                $created->push($tagihan);

                Notification::create([
                    'user_id' => $mahasiswa->user_id,
                    'type' => Notification::TYPE_TAGIHAN,
                    'title' => 'Tagihan Baru',
                    'message' => $tagihan->nama.' sebesar Rp'.number_format((float) $tagihan->jumlah, 0, ',', '.'),
                    'data' => ['tagihan_id' => $tagihan->id],
                ]);
            }
        });

        $sentCount = 0;

        if ($request->boolean('send_now')) {
            foreach ($created as $tagihan) {
                $sentCount += $this->sendWhatsApp($tagihan) ? 1 : 0;
            }
        }

        $message = $created->count().' tagihan berhasil dibuat.';
        if ($request->boolean('send_now')) {
            $message .= ' '.$sentCount.' pesan WhatsApp berhasil dikirim.';
        }

        return back()->with('success', $message);
    }

    public function resend(TagihanMahasiswa $tagihan): RedirectResponse
    {
        if (! $this->sendWhatsApp($tagihan)) {
            return back()->withErrors(['wa_error' => $tagihan->wa_error ?: 'Pengiriman WhatsApp gagal.']);
        }

        return back()->with('success', 'Pesan WhatsApp berhasil dikirim ulang.');
    }

    public function markPaid(TagihanMahasiswa $tagihan): RedirectResponse
    {
        $tagihan->update([
            'status' => TagihanMahasiswa::STATUS_SUDAH_BAYAR,
            'paid_at' => now(),
        ]);

        return back()->with('success', 'Tagihan berhasil ditandai sudah dibayar.');
    }

    public function cancel(TagihanMahasiswa $tagihan): RedirectResponse
    {
        $tagihan->update([
            'status' => TagihanMahasiswa::STATUS_BATAL,
            'paid_at' => null,
        ]);

        return back()->with('success', 'Tagihan berhasil dibatalkan.');
    }

    private function resolveStudents(array $validated)
    {
        $query = Mahasiswa::query()->with(['user', 'prodi'])->where('status', 'aktif');

        return match ($validated['target_type']) {
            'mahasiswa' => $query->whereKey($validated['mahasiswa_id'])->get(),
            'prodi' => $query->where('prodi_id', $validated['prodi_id'])->get(),
            'angkatan' => $query->where('angkatan', $validated['angkatan'])->get(),
            'rombel' => $query->where('rombel', mb_strtoupper($validated['rombel']))->get(),
        };
    }

    private function sendWhatsApp(TagihanMahasiswa $tagihan): bool
    {
        $tagihan->loadMissing(['mahasiswa.user']);
        $mahasiswa = $tagihan->mahasiswa;
        $phone = $mahasiswa->phone;

        if (! $phone) {
            $tagihan->update(['wa_error' => 'Nomor WhatsApp mahasiswa belum tersedia.']);

            return false;
        }

        $message = implode("\n", array_filter([
            'Yth. '.$mahasiswa->user->name,
            '',
            'Anda memiliki tagihan:',
            'Nama: '.$tagihan->nama,
            'Jumlah: Rp'.number_format((float) $tagihan->jumlah, 0, ',', '.'),
            'Jatuh tempo: '.$tagihan->jatuh_tempo->locale('id')->translatedFormat('d F Y'),
            $tagihan->keterangan ? 'Keterangan: '.$tagihan->keterangan : null,
            '',
            'Mohon segera melakukan pembayaran sesuai jadwal.',
        ]));

        try {
            $this->fonnteService->send($phone, $message);
            $tagihan->update([
                'wa_sent_at' => now(),
                'wa_error' => null,
            ]);

            return true;
        } catch (RuntimeException $exception) {
            $tagihan->update(['wa_error' => $exception->getMessage()]);

            return false;
        }
    }
}
