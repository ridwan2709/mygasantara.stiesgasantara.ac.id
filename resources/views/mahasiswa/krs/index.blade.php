<x-app-layout>
    <x-slot name="header">
        <span class="md:hidden">KRS</span>
        <span class="hidden md:inline">Kartu Rencana Studi (KRS)</span>
    </x-slot>

    @if(!$krs)
    <div class="card-saas p-8 text-center dark:bg-gray-800">
        <h3 class="text-lg font-semibold text-siakad-dark dark:text-white">KRS Belum Dibuat</h3>
        <p class="mt-2 text-sm text-siakad-secondary dark:text-gray-400">
            KRS untuk {{ $tahunAkademik?->tahun ?? 'tahun akademik aktif' }} {{ $tahunAkademik?->semester ?? '' }}
            akan dibuatkan oleh admin atau admin fakultas.
        </p>
    </div>
    @else
    <!-- Status Banner -->
    <div class="mb-8">
        <div class="bg-siakad-primary rounded-xl p-6 text-white">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div>
                    <p class="text-[10px] md:text-xs opacity-70 uppercase tracking-wider">Tahun Akademik Aktif</p>
                    <h3 class="text-xl font-bold mt-1">{{ $krs->tahunAkademik->tahun ?? '-' }} - {{ $krs->tahunAkademik->semester ?? '-' }}</h3>
                </div>
                <div class="flex items-center justify-between md:justify-end gap-6 border-t border-white/10 pt-4 md:border-0 md:pt-0">
                    <div class="text-center">
                        <p class="text-2xl font-bold">{{ $krs->krsDetail->sum(fn($d) => $d->kelas->mataKuliah->sks) }}</p>
                        <p class="text-xs opacity-70">Total SKS</p>
                    </div>
                    <div class="text-center">
                        @php
                            $statusColors = [
                                'approved' => 'bg-emerald-500/30 text-emerald-100',
                                'rejected' => 'bg-red-500/30 text-red-100',
                                'pending' => 'bg-amber-500/30 text-amber-100',
                                'draft' => 'bg-white/20 text-white',
                            ];
                        @endphp
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold {{ $statusColors[$krs->status] ?? 'bg-white/20' }}">
                            {{ ucfirst($krs->status) }}
                        </span>
                    </div>
                </div>
            </div>
            
            @if($krs->status == 'draft')
            <div class="mt-5 pt-5 border-t border-white/20">
                <p class="text-sm opacity-80">KRS sedang disiapkan oleh admin atau admin fakultas.</p>
            </div>
            @elseif($krs->status == 'rejected')
            <div class="mt-5 pt-5 border-t border-white/20">
                <p class="text-sm font-semibold text-red-200">❌ KRS Ditolak</p>
                <p class="mt-1 text-sm opacity-80">{{ $krs->catatan ?? 'Silakan hubungi admin atau admin fakultas untuk revisi KRS.' }}</p>
            </div>
            @elseif($krs->status == 'pending')
            <div class="mt-5 pt-5 border-t border-white/20">
                <p class="text-sm opacity-80">⏳ KRS Anda sedang menunggu persetujuan dari Dosen PA.</p>
            </div>
            @elseif($krs->status == 'approved')
            <div class="mt-5 pt-5 border-t border-white/20">
                <p class="text-sm opacity-80">✅ KRS Anda telah disetujui.</p>
            </div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Taken Classes -->
        <div class="lg:col-span-3">
            <div class="card-saas overflow-hidden">
                <div class="px-6 py-4 border-b border-siakad-light">
                    <h3 class="font-semibold text-siakad-dark">Mata Kuliah Diambil</h3>
                    <p class="text-xs text-siakad-secondary mt-1">{{ $krs->krsDetail->count() }} mata kuliah dipilih</p>
                </div>
                
                <div class="divide-y divide-siakad-light/50">
                    @forelse($krs->krsDetail as $detail)
                    <div class="p-4 flex items-center gap-4 hover:bg-siakad-light/20 transition">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-siakad-dark truncate">{{ $detail->kelas->mataKuliah->nama_mk }}</p>
                            <p class="text-xs text-siakad-secondary">{{ $detail->kelas->mataKuliah->kode_mk }} • {{ $detail->kelas->dosen->user->name }}</p>
                        </div>
                        <div class="text-center px-3">
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-siakad-primary/10 text-siakad-primary font-semibold text-sm">
                                {{ $detail->kelas->mataKuliah->sks }}
                            </span>
                            <p class="text-[10px] text-siakad-secondary mt-1">SKS</p>
                        </div>
                    </div>
                    @empty
                    <div class="py-12 text-center">
                        <div class="w-14 h-14 rounded-xl bg-siakad-light/50 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-7 h-7 text-siakad-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        </div>
                        <p class="text-siakad-secondary font-medium">Belum ada mata kuliah diambil</p>
                        <p class="text-xs text-siakad-secondary/70">Admin atau admin fakultas akan menyusun KRS Anda</p>
                    </div>
                    @endforelse
                </div>
                <!-- Total SKS Footer -->
                <div class="px-6 py-4 border-t border-siakad-light dark:border-slate-700 flex justify-between items-center" style="background-color: var(--bg-card);">
                    <span class="font-semibold text-siakad-dark">Total SKS Diambil</span>
                    <span class="font-bold text-siakad-primary text-lg">{{ $krs->krsDetail->sum(fn($d) => $d->kelas->mataKuliah->sks) }} SKS</span>
                </div>
            </div>
        </div>
    </div>
    @endif
</x-app-layout>
