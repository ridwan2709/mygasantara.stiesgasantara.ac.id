<x-app-layout>
    <x-slot name="header">
        Dokumen Penunjang Dosen
    </x-slot>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-300">
            <ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="mb-6">
        <a href="{{ route('admin.dosen.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-siakad-primary hover:underline dark:text-blue-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            Kembali ke Daftar Dosen
        </a>
    </div>

    <div class="mb-6 card-saas p-5 dark:bg-gray-800">
        <h2 class="text-lg font-semibold text-siakad-dark dark:text-white">{{ $dosen->user->name }}</h2>
        <p class="mt-1 text-sm text-siakad-secondary dark:text-gray-400">NIDN {{ $dosen->nidn }} · {{ $dosen->prodi->nama }}</p>
    </div>

    <div class="mb-6 card-saas p-5 dark:bg-gray-800">
        <h2 class="mb-4 text-base font-semibold text-siakad-dark dark:text-white">Tambah Tautan Dokumen</h2>
        <form action="{{ route('admin.dosen.dokumen.store', $dosen) }}" method="POST" class="grid grid-cols-1 gap-4 md:grid-cols-3 md:items-end">
            @csrf
            <div>
                <label for="nama_dokumen" class="mb-1 block text-sm font-medium text-siakad-secondary dark:text-gray-300">Nama dokumen</label>
                <input id="nama_dokumen" name="nama_dokumen" type="text" value="{{ old('nama_dokumen') }}" placeholder="Contoh: Ijazah S1" required maxlength="100" class="input-saas w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
            <div>
                <label for="link_url" class="mb-1 block text-sm font-medium text-siakad-secondary dark:text-gray-300">Link Google Drive</label>
                <input id="link_url" name="link_url" type="url" value="{{ old('link_url') }}" placeholder="https://drive.google.com/file/d/..." required maxlength="2048" class="input-saas w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                <p class="mt-1 text-xs text-siakad-secondary dark:text-gray-400">Pastikan akses Drive diatur agar siapa pun yang memiliki link dapat melihat.</p>
            </div>
            <button type="submit" class="btn-primary-saas inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 13a5 5 0 007.07 0l3-3a5 5 0 00-7.07-7.07l-1.72 1.71M14 11a5 5 0 00-7.07 0l-3 3a5 5 0 007.07 7.07l1.71-1.71"></path></svg>
                Simpan Link
            </button>
        </form>
    </div>

    <div class="card-saas overflow-hidden dark:bg-gray-800">
        <div class="border-b border-siakad-light px-5 py-4 dark:border-gray-700">
            <h2 class="font-semibold text-siakad-dark dark:text-white">Dokumen Tersimpan</h2>
        </div>
        @if($dokumen->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-siakad-secondary dark:text-gray-400">Belum ada dokumen penunjang untuk dosen ini.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-siakad-light/30 text-left text-xs font-semibold uppercase tracking-wider text-siakad-secondary dark:bg-gray-900 dark:text-gray-400">
                            <th class="px-5 py-3">Nama Dokumen</th>
                            <th class="px-5 py-3">Link Dokumen</th>
                            <th class="px-5 py-3">Tanggal Unggah</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-siakad-light dark:divide-gray-700">
                        @foreach($dokumen as $item)
                            <tr>
                                <td class="px-5 py-4 text-sm font-medium text-siakad-dark dark:text-white">{{ $item->nama_dokumen }}</td>
                                <td class="max-w-md px-5 py-4 text-sm">
                                    @if($item->link_url)
                                        <a href="{{ $item->link_url }}" target="_blank" rel="noopener noreferrer" class="break-all font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                                            Buka link dokumen
                                        </a>
                                    @else
                                        <span class="text-amber-700 dark:text-amber-400">Tambahkan ulang sebagai link Google Drive</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-sm text-siakad-secondary dark:text-gray-300">{{ $item->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($item->link_url)
                                            <a href="{{ $item->link_url }}" target="_blank" rel="noopener noreferrer" class="rounded-lg px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30" aria-label="Buka {{ $item->nama_dokumen }}">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                            </a>
                                        @endif
                                        <form action="{{ route('admin.dosen.dokumen.destroy', [$dosen, $item]) }}" method="POST" onsubmit="return confirm('Hapus dokumen ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
