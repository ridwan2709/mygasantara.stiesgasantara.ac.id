<x-app-layout>
    <x-slot name="header">Riwayat Honorarium</x-slot>

    <div class="card-saas overflow-hidden dark:bg-gray-800">
        <div class="border-b border-siakad-light p-5 dark:border-gray-700">
            <h2 class="text-lg font-semibold">{{ $dosen->user->name }}</h2>
            <p class="text-sm text-siakad-secondary dark:text-gray-400">NIDN {{ $dosen->nidn }} · Slip honorarium per periode</p>
        </div>
        <div class="overflow-x-auto">
            <table class="table-saas w-full">
                <thead><tr class="bg-siakad-light/30 text-left text-xs uppercase dark:bg-gray-900"><th class="px-5 py-3">Periode</th><th class="px-5 py-3 text-right">Kehadiran</th><th class="px-5 py-3 text-right">Honor mengajar</th><th class="px-5 py-3 text-right">Insentif</th><th class="px-5 py-3 text-right">Total</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Slip</th></tr></thead>
                <tbody>
                    @forelse($honorariumList as $honorarium)
                        <tr class="border-t border-siakad-light dark:border-gray-700">
                            <td class="px-5 py-3">{{ \Carbon\Carbon::create($honorarium->tahun, $honorarium->bulan)->locale('id')->monthName }} {{ $honorarium->tahun }}</td>
                            <td class="px-5 py-3 text-right">{{ $honorarium->total_kehadiran }}</td>
                            <td class="px-5 py-3 text-right">Rp{{ number_format((float) $honorarium->total_honor_mengajar, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right">Rp{{ number_format((float) $honorarium->total_insentif_manual, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right font-semibold">Rp{{ number_format((float) $honorarium->total_penerimaan, 0, ',', '.') }}</td>
                            <td class="px-5 py-3">{{ $honorarium->status_pembayaran === 'paid' ? 'Dibayar' : 'Draft' }}</td>
                            <td class="px-5 py-3"><a href="{{ route('dosen.honorarium.slip', $honorarium) }}" class="text-siakad-primary underline dark:text-blue-400">Unduh PDF</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-8 text-center text-siakad-secondary">Belum ada rekap honorarium.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-5">{{ $honorariumList->links() }}</div>
    </div>
</x-app-layout>
