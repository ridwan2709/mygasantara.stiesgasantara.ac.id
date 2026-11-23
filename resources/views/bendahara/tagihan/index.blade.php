<x-app-layout>
    <x-slot name="header">Tagihan Mahasiswa</x-slot>

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-emerald-700 dark:border-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-4 text-red-700 dark:border-red-700 dark:bg-red-900/20 dark:text-red-300">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="card-saas p-6 lg:col-span-1 dark:bg-gray-800">
            <h2 class="mb-4 text-lg font-semibold text-siakad-dark dark:text-white">Buat Tagihan</h2>
            <form action="{{ route('bendahara.tagihan.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium text-siakad-secondary">Target</label>
                    <select name="target_type" id="targetType" class="input-saas w-full dark:bg-gray-900 dark:text-white">
                        <option value="mahasiswa">Mahasiswa tertentu</option>
                        <option value="prodi">Seluruh Prodi</option>
                        <option value="angkatan">Seluruh Angkatan</option>
                        <option value="rombel">Seluruh Rombel</option>
                    </select>
                </div>
                <div id="targetMahasiswa">
                    <label class="mb-1 block text-sm font-medium text-siakad-secondary">Mahasiswa</label>
                    <select name="mahasiswa_id" class="input-saas w-full dark:bg-gray-900 dark:text-white">
                        <option value="">Pilih mahasiswa</option>
                        @foreach($mahasiswaList as $mahasiswa)
                            <option value="{{ $mahasiswa->id }}">{{ $mahasiswa->nim }} - {{ $mahasiswa->user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="targetProdi" class="hidden">
                    <label class="mb-1 block text-sm font-medium text-siakad-secondary">Prodi</label>
                    <select name="prodi_id" class="input-saas w-full dark:bg-gray-900 dark:text-white">
                        @foreach($prodiList as $prodi)<option value="{{ $prodi->id }}">{{ $prodi->nama }}</option>@endforeach
                    </select>
                </div>
                <div id="targetAngkatan" class="hidden">
                    <label class="mb-1 block text-sm font-medium text-siakad-secondary">Angkatan</label>
                    <select name="angkatan" class="input-saas w-full dark:bg-gray-900 dark:text-white">
                        @foreach($angkatanList as $angkatan)<option value="{{ $angkatan }}">{{ $angkatan }}</option>@endforeach
                    </select>
                </div>
                <div id="targetRombel" class="hidden">
                    <label class="mb-1 block text-sm font-medium text-siakad-secondary">Rombel</label>
                    <select name="rombel" class="input-saas w-full dark:bg-gray-900 dark:text-white">
                        @foreach($rombelList as $rombel)<option value="{{ $rombel }}">{{ $rombel }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-siakad-secondary">Nama Tagihan</label>
                    <input type="text" name="nama" value="{{ old('nama') }}" required maxlength="255" class="input-saas w-full dark:bg-gray-900 dark:text-white" placeholder="Contoh: SPP Semester Ganjil">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-siakad-secondary">Jumlah</label>
                    <input type="number" name="jumlah" value="{{ old('jumlah') }}" required min="0" step="100" class="input-saas w-full dark:bg-gray-900 dark:text-white" placeholder="0">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-siakad-secondary">Tanggal Jatuh Tempo</label>
                    <input type="date" name="jatuh_tempo" value="{{ old('jatuh_tempo') }}" required class="input-saas w-full dark:bg-gray-900 dark:text-white">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-siakad-secondary">Keterangan</label>
                    <textarea name="keterangan" rows="3" maxlength="1000" class="input-saas w-full dark:bg-gray-900 dark:text-white">{{ old('keterangan') }}</textarea>
                </div>
                <label class="flex items-center gap-2 text-sm text-siakad-dark dark:text-gray-300">
                    <input type="checkbox" name="send_now" value="1" checked class="rounded">
                    Kirim WhatsApp Fonnte saat tagihan dibuat
                </label>
                <button type="submit" class="btn-primary-saas w-full rounded-lg px-4 py-2.5 text-sm font-medium">Buat Tagihan</button>
            </form>
        </div>

        <div class="card-saas overflow-hidden lg:col-span-2 dark:bg-gray-800">
            <div class="border-b border-siakad-light p-5 dark:border-gray-700">
                <form method="GET" class="grid grid-cols-1 gap-3 md:grid-cols-4">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama atau NIM" class="input-saas px-3 py-2 text-sm dark:bg-gray-900 dark:text-white">
                    <select name="status" class="input-saas px-3 py-2 text-sm dark:bg-gray-900 dark:text-white">
                        <option value="">Semua Status</option>
                        <option value="belum_bayar" @selected(request('status') === 'belum_bayar')>Belum Bayar</option>
                        <option value="sudah_bayar" @selected(request('status') === 'sudah_bayar')>Sudah Bayar</option>
                        <option value="batal" @selected(request('status') === 'batal')>Batal</option>
                    </select>
                    <select name="prodi_id" class="input-saas px-3 py-2 text-sm dark:bg-gray-900 dark:text-white">
                        <option value="">Semua Prodi</option>
                        @foreach($prodiList as $prodi)<option value="{{ $prodi->id }}" @selected((string) request('prodi_id') === (string) $prodi->id)>{{ $prodi->nama }}</option>@endforeach
                    </select>
                    <button type="submit" class="btn-primary-saas rounded-lg px-4 py-2 text-sm">Filter</button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full table-saas">
                    <thead>
                        <tr class="bg-siakad-light/30 dark:bg-gray-900">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-siakad-secondary">Mahasiswa</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-siakad-secondary">Tagihan</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-siakad-secondary">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-siakad-secondary">WhatsApp</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-siakad-secondary">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-siakad-light dark:divide-gray-700">
                        @forelse($tagihanList as $tagihan)
                            <tr>
                                <td class="px-4 py-3 text-sm">
                                    <p class="font-medium text-siakad-dark dark:text-white">{{ $tagihan->mahasiswa->user->name }}</p>
                                    <p class="text-xs text-siakad-secondary">{{ $tagihan->mahasiswa->nim }} · {{ $tagihan->mahasiswa->prodi->nama ?? '-' }}</p>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <p class="font-medium text-siakad-dark dark:text-white">{{ $tagihan->nama }}</p>
                                    <p class="text-xs text-siakad-secondary">Rp{{ number_format((float) $tagihan->jumlah, 0, ',', '.') }} · Tempo {{ $tagihan->jatuh_tempo->format('d/m/Y') }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    @if($tagihan->status === 'sudah_bayar')
                                        <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">Sudah Bayar</span>
                                    @elseif($tagihan->status === 'batal')
                                        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">Batal</span>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-700">Belum Bayar</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    @if($tagihan->wa_sent_at)
                                        <span class="text-emerald-600">Terkirim {{ $tagihan->wa_sent_at->format('d/m/Y H:i') }}</span>
                                    @else
                                        <span class="text-red-600">{{ $tagihan->wa_error ?: 'Belum dikirim' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <form action="{{ route('bendahara.tagihan.resend', $tagihan) }}" method="POST">@csrf
                                            <button type="submit" class="rounded-lg px-2 py-1 text-xs text-siakad-primary hover:bg-siakad-primary/10">Kirim WA</button>
                                        </form>
                                        @if($tagihan->status !== 'sudah_bayar')
                                            <form action="{{ route('bendahara.tagihan.paid', $tagihan) }}" method="POST">@csrf @method('PATCH')
                                                <button type="submit" class="rounded-lg px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">Sudah Bayar</button>
                                            </form>
                                        @endif
                                        @if($tagihan->status !== 'batal')
                                            <form action="{{ route('bendahara.tagihan.cancel', $tagihan) }}" method="POST">@csrf @method('PATCH')
                                                <button type="submit" class="rounded-lg px-2 py-1 text-xs text-red-700 hover:bg-red-50">Batalkan</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-sm text-siakad-secondary">Belum ada tagihan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($tagihanList->hasPages())<div class="border-t border-siakad-light p-4 dark:border-gray-700">{{ $tagihanList->links() }}</div>@endif
        </div>
    </div>

    <script>
        const targetType = document.getElementById('targetType');
        function updateTargets() {
            ['Mahasiswa', 'Prodi', 'Angkatan', 'Rombel'].forEach((name) => {
                document.getElementById(`target${name}`).classList.toggle('hidden', targetType.value !== name.toLowerCase());
            });
        }
        targetType.addEventListener('change', updateTargets);
        updateTargets();
    </script>
</x-app-layout>
