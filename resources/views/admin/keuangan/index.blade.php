<x-app-layout>
    <x-slot name="header">Honorarium Dosen</x-slot>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-300">
            <ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card-saas mb-6 p-5 dark:bg-gray-800">
        <form method="GET" action="{{ route('admin.honorarium.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="bulan" class="mb-1 block text-sm font-medium">Bulan</label>
                <select id="bulan" name="bulan" class="input-saas rounded-lg px-3 py-2">
                    @for($month = 1; $month <= 12; $month++)
                        <option value="{{ $month }}" @selected($bulan === $month)>{{ \Carbon\Carbon::create()->month($month)->locale('id')->monthName }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label for="tahun" class="mb-1 block text-sm font-medium">Tahun</label>
                <input id="tahun" name="tahun" type="number" min="2000" max="2100" value="{{ $tahun }}" class="input-saas w-28 rounded-lg px-3 py-2">
            </div>
            <button class="btn-primary-saas rounded-lg px-4 py-2">Tampilkan</button>
            <a href="{{ route('admin.honorarium.export.pdf', ['bulan' => $bulan, 'tahun' => $tahun]) }}" class="btn-ghost-saas rounded-lg px-4 py-2">Export PDF</a>
            <a href="{{ route('admin.honorarium.export.excel', ['bulan' => $bulan, 'tahun' => $tahun]) }}" class="btn-ghost-saas rounded-lg px-4 py-2">Export Excel</a>
        </form>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="card-saas p-5 dark:bg-gray-800">
            <h2 class="mb-1 text-lg font-semibold">Pengaturan tarif</h2>
            @if($tarifAktif)
                <p class="mb-4 text-sm text-siakad-secondary dark:text-gray-400">
                    Tarif aktif sejak {{ $tarifAktif->berlaku_mulai->format('d/m/Y') }}:
                    Rp{{ number_format((float) $tarifAktif->nominal, 0, ',', '.') }}/{{ $tarifAktif->basis_tarif === 'per_sks' ? 'SKS' : 'kehadiran' }}.
                </p>
            @else
                <p class="mb-4 text-sm text-amber-700 dark:text-amber-300">Belum ada tarif aktif.</p>
            @endif
            <form method="POST" action="{{ route('admin.honorarium.tarif.store') }}" class="space-y-3">
                @csrf
                <label class="block text-sm">Basis tarif
                    <select name="basis_tarif" required class="input-saas mt-1 w-full rounded-lg px-3 py-2">
                        <option value="per_sks">Per SKS</option>
                        <option value="per_kehadiran">Per kehadiran</option>
                    </select>
                </label>
                <label class="block text-sm">Nominal tarif
                    <input name="nominal" type="number" min="1" step="0.01" required class="input-saas mt-1 w-full rounded-lg px-3 py-2">
                </label>
                <label class="block text-sm">Berlaku mulai
                    <input name="berlaku_mulai" type="date" required class="input-saas mt-1 w-full rounded-lg px-3 py-2">
                </label>
                <button class="btn-primary-saas rounded-lg px-4 py-2">Simpan tarif</button>
            </form>
        </section>

        <section class="card-saas p-5 dark:bg-gray-800">
            <h2 class="mb-4 text-lg font-semibold">Catat kehadiran mengajar</h2>
            <form method="POST" action="{{ route('admin.honorarium.kehadiran.store') }}" class="space-y-3">
                @csrf
                <label class="block text-sm">Dosen
                    <select name="dosen_id" required class="input-saas mt-1 w-full rounded-lg px-3 py-2">
                        <option value="">Pilih dosen</option>
                        @foreach($dosenList as $dosen)<option value="{{ $dosen->id }}">{{ $dosen->user->name }} ({{ $dosen->nidn }})</option>@endforeach
                    </select>
                </label>
                <label class="block text-sm">Mata kuliah
                    <select name="mata_kuliah_id" required class="input-saas mt-1 w-full rounded-lg px-3 py-2">
                        <option value="">Pilih mata kuliah</option>
                        @foreach($mataKuliahList as $mataKuliah)<option value="{{ $mataKuliah->id }}">{{ $mataKuliah->kode_mk }} — {{ $mataKuliah->nama_mk }} ({{ $mataKuliah->sks }} SKS)</option>@endforeach
                    </select>
                </label>
                <label class="block text-sm">Kelas
                    <select name="kelas_id" required class="input-saas mt-1 w-full rounded-lg px-3 py-2">
                        <option value="">Pilih kelas</option>
                        @foreach($kelasList as $kelas)<option value="{{ $kelas->id }}">{{ $kelas->nama_kelas }} — {{ $kelas->dosen->user->name }} / {{ $kelas->mataKuliah->nama_mk }}</option>@endforeach
                    </select>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block text-sm">Tanggal<input name="tanggal" type="date" required class="input-saas mt-1 w-full rounded-lg px-3 py-2"></label>
                    <label class="block text-sm">Status hadir
                        <select name="status_hadir" class="input-saas mt-1 w-full rounded-lg px-3 py-2">
                            @foreach(['hadir', 'izin', 'sakit', 'alpa'] as $status)<option value="{{ $status }}">{{ ucfirst($status) }}</option>@endforeach
                        </select>
                    </label>
                    <label class="block text-sm">Jam mulai<input name="jam_mulai" type="time" required class="input-saas mt-1 w-full rounded-lg px-3 py-2"></label>
                    <label class="block text-sm">Jam selesai<input name="jam_selesai" type="time" required class="input-saas mt-1 w-full rounded-lg px-3 py-2"></label>
                </div>
                <label class="block text-sm">Keterangan<textarea name="keterangan" rows="2" class="input-saas mt-1 w-full rounded-lg px-3 py-2"></textarea></label>
                <button class="btn-primary-saas rounded-lg px-4 py-2">Simpan untuk diverifikasi</button>
            </form>
        </section>

        <section class="card-saas p-5 dark:bg-gray-800">
            <h2 class="mb-4 text-lg font-semibold">Tambah insentif manual</h2>
            <form method="POST" action="{{ route('admin.honorarium.insentif.store') }}" class="space-y-3">
                @csrf
                <label class="block text-sm">Dosen
                    <select name="dosen_id" required class="input-saas mt-1 w-full rounded-lg px-3 py-2">
                        <option value="">Pilih dosen</option>
                        @foreach($dosenList as $dosen)<option value="{{ $dosen->id }}">{{ $dosen->user->name }} ({{ $dosen->nidn }})</option>@endforeach
                    </select>
                </label>
                <label class="block text-sm">Jenis insentif<input name="jenis_insentif" required maxlength="150" class="input-saas mt-1 w-full rounded-lg px-3 py-2"></label>
                <label class="block text-sm">Nominal (Rp)<input name="nominal" type="number" min="1" step="0.01" required class="input-saas mt-1 w-full rounded-lg px-3 py-2"></label>
                <label class="block text-sm">Tanggal<input name="tanggal" type="date" required class="input-saas mt-1 w-full rounded-lg px-3 py-2"></label>
                <label class="block text-sm">Keterangan<textarea name="keterangan" rows="2" class="input-saas mt-1 w-full rounded-lg px-3 py-2"></textarea></label>
                <button class="btn-primary-saas rounded-lg px-4 py-2">Tambah insentif</button>
            </form>
        </section>
    </div>

    <section class="card-saas mt-6 overflow-hidden dark:bg-gray-800">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-siakad-light p-5 dark:border-gray-700">
            <div><h2 class="text-lg font-semibold">Rekap {{ \Carbon\Carbon::create($tahun, $bulan)->locale('id')->monthName }} {{ $tahun }}</h2><p class="text-sm text-siakad-secondary dark:text-gray-400">Kehadiran yang dihitung hanya status hadir dan sudah diverifikasi.</p></div>
            <form method="POST" action="{{ route('admin.honorarium.generate') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <input type="hidden" name="bulan" value="{{ $bulan }}">
                <input type="hidden" name="tahun" value="{{ $tahun }}">
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="table-saas w-full">
                <thead><tr class="bg-siakad-light/30 text-left text-xs uppercase dark:bg-gray-900"><th class="px-5 py-3">Dosen</th><th class="px-5 py-3 text-right">Hadir</th><th class="px-5 py-3 text-right">Honor mengajar</th><th class="px-5 py-3 text-right">Insentif</th><th class="px-5 py-3 text-right">Total</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Aksi</th></tr></thead>
                <tbody>
                    @forelse($honorariumList as $honorarium)
                        <tr class="border-t border-siakad-light dark:border-gray-700">
                            <td class="px-5 py-3">{{ $honorarium->dosen->user->name }}<div class="text-xs text-siakad-secondary">{{ $honorarium->dosen->nidn }}</div></td>
                            <td class="px-5 py-3 text-right">{{ $honorarium->total_kehadiran }}</td>
                            <td class="px-5 py-3 text-right">Rp{{ number_format((float) $honorarium->total_honor_mengajar, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right">Rp{{ number_format((float) $honorarium->total_insentif_manual, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right font-semibold">Rp{{ number_format((float) $honorarium->total_penerimaan, 0, ',', '.') }}</td>
                            <td class="px-5 py-3">{{ $honorarium->status_pembayaran === 'paid' ? 'Dibayar' : 'Draft' }}</td>
                            <td class="px-5 py-3">
                                @if($honorarium->status_pembayaran === 'draft')
                                    <form method="POST" action="{{ route('admin.honorarium.pay', $honorarium) }}" onsubmit="return confirm('Tandai honorarium ini sudah dibayar?')">
                                        @csrf @method('PATCH')
                                        <button class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-medium text-white">Tandai dibayar</button>
                                    </form>
                                @else
                                    <span class="text-xs text-siakad-secondary">Dibayar {{ $honorarium->tanggal_bayar?->format('d/m/Y') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-8 text-center text-siakad-secondary">Belum ada rekap. Gunakan tombol generate setelah data kehadiran diverifikasi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card-saas mt-6 overflow-hidden dark:bg-gray-800">
        <div class="border-b border-siakad-light p-5 dark:border-gray-700"><h2 class="text-lg font-semibold">Validasi kehadiran mengajar</h2></div>
        <div class="overflow-x-auto">
            <table class="table-saas w-full">
                <thead><tr class="bg-siakad-light/30 text-left text-xs uppercase dark:bg-gray-900"><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Dosen / Mata kuliah</th><th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Kehadiran</th><th class="px-5 py-3">Verifikasi</th><th class="px-5 py-3">Aksi</th></tr></thead>
                <tbody>
                    @forelse($kehadiranList as $kehadiran)
                        <tr class="border-t border-siakad-light dark:border-gray-700">
                            <td class="px-5 py-3">{{ $kehadiran->tanggal->format('d/m/Y') }}</td>
                            <td class="px-5 py-3">{{ $kehadiran->dosen->user->name }}<div class="text-xs text-siakad-secondary">{{ $kehadiran->mataKuliah->nama_mk }}</div></td>
                            <td class="px-5 py-3">{{ substr($kehadiran->jam_mulai, 0, 5) }}–{{ substr($kehadiran->jam_selesai, 0, 5) }}</td>
                            <td class="px-5 py-3">{{ ucfirst($kehadiran->status_hadir) }}</td>
                            <td class="px-5 py-3">{{ ucfirst($kehadiran->status_verifikasi) }}</td>
                            <td class="px-5 py-3">
                                @if($kehadiran->status_verifikasi === 'pending')
                                    <div class="flex gap-2">
                                        @foreach(['approved' => 'Setujui', 'rejected' => 'Tolak'] as $status => $label)
                                            <form method="POST" action="{{ route('admin.honorarium.kehadiran.verify', $kehadiran) }}">
                                                @csrf @method('PATCH')<input type="hidden" name="status_verifikasi" value="{{ $status }}">
                                                <button class="rounded-lg {{ $status === 'approved' ? 'bg-emerald-600' : 'bg-red-600' }} px-3 py-2 text-xs font-medium text-white">{{ $label }}</button>
                                            </form>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-xs text-siakad-secondary">{{ $kehadiran->diverifikasi_pada?->format('d/m/Y H:i') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-siakad-secondary">Tidak ada kehadiran mengajar pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-5">{{ $kehadiranList->links() }}</div>
    </section>

    <section class="card-saas mt-6 overflow-hidden dark:bg-gray-800">
        <div class="border-b border-siakad-light p-5 dark:border-gray-700"><h2 class="text-lg font-semibold">Insentif manual pada periode ini</h2></div>
        <div class="overflow-x-auto">
            <table class="table-saas w-full">
                <thead><tr class="bg-siakad-light/30 text-left text-xs uppercase dark:bg-gray-900"><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Dosen</th><th class="px-5 py-3">Jenis</th><th class="px-5 py-3 text-right">Nominal</th><th class="px-5 py-3">Keterangan</th></tr></thead>
                <tbody>
                    @forelse($insentifList as $insentif)
                        <tr class="border-t border-siakad-light dark:border-gray-700"><td class="px-5 py-3">{{ $insentif->tanggal->format('d/m/Y') }}</td><td class="px-5 py-3">{{ $insentif->dosen->user->name }}</td><td class="px-5 py-3">{{ $insentif->jenis_insentif }}</td><td class="px-5 py-3 text-right">Rp{{ number_format((float) $insentif->nominal, 0, ',', '.') }}</td><td class="px-5 py-3">{{ $insentif->keterangan }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-6 text-center text-siakad-secondary">Belum ada insentif manual untuk periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-app-layout>
