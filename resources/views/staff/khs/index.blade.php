<x-app-layout>
    <x-slot name="header">Kartu Hasil Studi (KHS)</x-slot>

    <section class="card-saas mb-6 p-5 dark:bg-gray-800">
        <div class="mb-4">
            <h1 class="text-xl font-semibold text-siakad-dark dark:text-white">KHS Mahasiswa</h1>
            <p class="mt-1 text-sm text-siakad-secondary dark:text-gray-400">Daftar semester dengan KRS yang telah disetujui.</p>
        </div>
        <form method="GET" action="{{ route('staff.khs.index') }}" class="flex flex-wrap items-end gap-3">
            <label for="search" class="min-w-64 flex-1 text-sm font-medium text-siakad-dark dark:text-gray-300">
                Cari mahasiswa
                <input id="search" name="search" type="search" value="{{ $search }}" placeholder="Nama atau NIM"
                    class="input-saas mt-1 w-full rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
            </label>
            <button type="submit" class="btn-primary-saas rounded-lg px-4 py-2">Cari</button>
            @if($search !== '')
                <a href="{{ route('staff.khs.index') }}" class="btn-ghost-saas rounded-lg px-4 py-2">Reset</a>
            @endif
        </form>
    </section>

    <section class="card-saas overflow-hidden dark:bg-gray-800">
        <div class="overflow-x-auto">
            <table class="table-saas w-full">
                <thead>
                    <tr class="bg-siakad-light/30 text-left text-xs uppercase dark:bg-gray-900">
                        <th class="px-5 py-3">Mahasiswa</th>
                        <th class="px-5 py-3">Tahun Akademik</th>
                        <th class="px-5 py-3 text-right">IPS</th>
                        <th class="px-5 py-3 text-right">Total SKS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($khsList as $item)
                        <tr class="border-t border-siakad-light dark:border-gray-700">
                            <td class="px-5 py-3">
                                <p class="font-medium text-siakad-dark dark:text-white">{{ $item['mahasiswa']->user->name }}</p>
                                <p class="text-xs text-siakad-secondary dark:text-gray-400">{{ $item['mahasiswa']->nim }}</p>
                            </td>
                            <td class="px-5 py-3 text-siakad-dark dark:text-gray-300">
                                {{ $item['tahun_akademik']?->tahun ?? '-' }}
                                <span class="text-sm text-siakad-secondary dark:text-gray-400">{{ $item['tahun_akademik']?->semester }}</span>
                            </td>
                            <td class="px-5 py-3 text-right font-semibold text-siakad-dark dark:text-white">{{ number_format($item['ips'], 2) }}</td>
                            <td class="px-5 py-3 text-right text-siakad-dark dark:text-gray-300">{{ $item['total_sks'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-10 text-center text-sm text-siakad-secondary dark:text-gray-400">
                                @if($search !== '')
                                    Tidak ada KHS yang cocok dengan pencarian "{{ $search }}".
                                @else
                                    Belum ada KRS yang disetujui, sehingga KHS belum tersedia.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($khsList->hasPages())
            <div class="border-t border-siakad-light p-5 dark:border-gray-700">{{ $khsList->links() }}</div>
        @endif
    </section>
</x-app-layout>
