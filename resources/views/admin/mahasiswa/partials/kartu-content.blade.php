<div class="w-[540px] max-w-full overflow-hidden rounded-lg border border-slate-300 bg-white shadow-xl">
    <div class="h-[130px] bg-[#a53041] pt-5 text-center text-white">
        <p class="text-base font-bold">STIES Gasantara</p>
        <p class="mt-2 text-xs tracking-[0.25em]">KARTU TANDA MAHASISWA</p>
    </div>
    <div class="-mt-2 px-8 pb-6">
        <div class="border-t border-slate-200 pt-5 text-center">
            <h2 class="text-xl font-bold text-slate-800">{{ $mahasiswa->user->name ?? '-' }}</h2>
            <p class="mt-1 font-mono text-base font-bold text-[#a53041]">{{ $mahasiswa->nim }}</p>
        </div>
        <dl class="mt-5 divide-y divide-slate-100 border-t border-slate-200 text-sm">
            <div class="flex py-3"><dt class="w-28 text-slate-500">Prodi</dt><dd class="font-semibold text-slate-800">{{ $mahasiswa->prodi->nama ?? '-' }}</dd></div>
            <div class="flex py-3"><dt class="w-28 text-slate-500">Angkatan</dt><dd class="font-semibold text-slate-800">{{ $mahasiswa->angkatan }}</dd></div>
            @if($mahasiswa->rombel)<div class="flex py-3"><dt class="w-28 text-slate-500">Rombel</dt><dd class="font-semibold text-slate-800">{{ $mahasiswa->rombel }}</dd></div>@endif
            <div class="flex py-3"><dt class="w-28 text-slate-500">Status</dt><dd class="font-semibold text-slate-800">{{ ucfirst($mahasiswa->status) }}</dd></div>
        </dl>
    </div>
</div>
