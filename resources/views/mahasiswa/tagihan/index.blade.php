<x-app-layout>
    <x-slot name="header">Tagihan Saya</x-slot>

    <div class="card-saas overflow-hidden dark:bg-gray-800">
        <div class="overflow-x-auto">
            <table class="w-full table-saas">
                <thead>
                    <tr class="bg-siakad-light/30 dark:bg-gray-900">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-siakad-secondary">Tagihan</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-siakad-secondary">Jumlah</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-siakad-secondary">Jatuh Tempo</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-siakad-secondary">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-siakad-light dark:divide-gray-700">
                    @forelse($tagihanList as $tagihan)
                        <tr>
                            <td class="px-5 py-4">
                                <p class="font-medium text-siakad-dark dark:text-white">{{ $tagihan->nama }}</p>
                                <p class="text-xs text-siakad-secondary">{{ $tagihan->keterangan ?: '-' }}</p>
                            </td>
                            <td class="px-5 py-4 text-sm font-semibold text-siakad-dark dark:text-white">Rp{{ number_format((float) $tagihan->jumlah, 0, ',', '.') }}</td>
                            <td class="px-5 py-4 text-sm text-siakad-secondary">{{ $tagihan->jatuh_tempo->format('d/m/Y') }}</td>
                            <td class="px-5 py-4">
                                @if($tagihan->status === 'sudah_bayar')
                                    <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">Sudah Bayar</span>
                                @elseif($tagihan->status === 'batal')
                                    <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">Batal</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-700">Belum Bayar</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-siakad-secondary">Belum ada tagihan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tagihanList->hasPages())<div class="border-t border-siakad-light p-4 dark:border-gray-700">{{ $tagihanList->links() }}</div>@endif
    </div>
</x-app-layout>
