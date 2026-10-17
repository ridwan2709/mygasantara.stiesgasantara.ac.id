<div class="mb-6 rounded-xl border border-siakad-light bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
    <div class="mb-3">
        <h3 class="text-sm font-semibold text-siakad-dark dark:text-white">Impor {{ $importLabel }} dari CSV</h3>
        <p class="mt-1 text-xs text-siakad-secondary dark:text-gray-400">Template berisi dua baris contoh bertanda CONTOH yang otomatis diabaikan saat impor. Salin dan sesuaikan contoh, lalu hapus penandanya untuk mengimpor. Data yang sudah ada akan ditolak dan seluruh file dibatalkan jika ada baris yang tidak valid.</p>
    </div>

    @if(session('import_success'))
        <div class="mb-3 rounded-lg border border-green-400 bg-green-100 p-3 text-sm text-green-700">{{ session('import_success') }}</div>
    @endif

    @if(session('import_failure') || $errors->masterDataImport->any())
        <div role="alert" class="mb-3 rounded-lg border border-red-400 bg-red-50 p-4 text-sm text-red-800 dark:border-red-700 dark:bg-red-900/20 dark:text-red-200">
            <p class="font-semibold">{{ session('import_failure', 'Impor gagal. Periksa dan perbaiki kesalahan berikut.') }}</p>
            @if($errors->masterDataImport->any())
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach($errors->masterDataImport->all() as $error)
                        <li class="whitespace-pre-line">{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <form action="{{ route('admin.master-data.import', $importType) }}" method="POST" enctype="multipart/form-data" class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-end">
            @csrf
            <label class="flex-1 text-xs font-medium text-siakad-secondary dark:text-gray-400">
                File CSV (maksimal 10 MB)
                <input type="file" name="file" accept=".csv,text/csv" required class="mt-1 block w-full rounded-lg border border-siakad-light px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
            </label>
            <button type="submit" class="btn-primary-saas rounded-lg px-4 py-2 text-sm font-medium">Impor</button>
        </form>
        <a href="{{ route('admin.master-data.template', $importType) }}" class="btn-ghost-saas rounded-lg border border-siakad-light px-4 py-2 text-center text-sm font-medium dark:border-gray-700 dark:text-white">
            <svg class="mr-2 inline h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" title="Unduh Template"></path></svg>
        </a>
    </div>
</div>
