<x-app-layout>
    <x-slot name="header">Tarif Honor Pegawai</x-slot>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-300">
            <ul class="list-inside list-disc">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="card-saas p-5 dark:bg-gray-800">
            <h2 class="mb-1 text-lg font-semibold text-siakad-dark dark:text-white">Atur tarif baru</h2>
            <p class="mb-4 text-sm text-siakad-secondary dark:text-gray-400">Tarif dihitung per hari hadir dan berlaku untuk pegawai terpilih.</p>
            @if($pegawaiList->isEmpty())
                <p class="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                    Belum ada pegawai dengan role superadmin, bendahara, atau staff.
                </p>
            @endif
            <form method="POST" action="{{ route('admin.honorarium.tarif-pegawai.store') }}" class="space-y-3">
                @csrf
                <label class="block text-sm text-siakad-dark dark:text-gray-300">
                    Pegawai
                    <select name="user_id" required class="input-saas mt-1 w-full rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                        <option value="">Pilih pegawai</option>
                        @foreach($pegawaiList as $pegawai)
                            <option value="{{ $pegawai->id }}" @selected(old('user_id') == $pegawai->id)>{{ $pegawai->name }} ({{ $pegawai->email }})</option>
                        @endforeach
                    </select>
                </label>
                @error('user_id')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                <label class="block text-sm text-siakad-dark dark:text-gray-300">
                    Nominal per hari hadir
                    <input name="nominal" type="number" min="0.01" step="0.01" value="{{ old('nominal') }}" required class="input-saas mt-1 w-full rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                </label>
                @error('nominal')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                <label class="block text-sm text-siakad-dark dark:text-gray-300">
                    Berlaku mulai
                    <input name="berlaku_mulai" type="date" value="{{ old('berlaku_mulai') }}" required class="input-saas mt-1 w-full rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                </label>
                @error('berlaku_mulai')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                <button type="submit" @disabled($pegawaiList->isEmpty()) class="btn-primary-saas rounded-lg px-4 py-2 disabled:cursor-not-allowed disabled:opacity-50">Simpan tarif</button>
            </form>
        </section>

        <section class="card-saas overflow-hidden dark:bg-gray-800 xl:col-span-2">
            <div class="border-b border-siakad-light px-5 py-4 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-siakad-dark dark:text-white">Tarif aktif</h2>
                <p class="text-sm text-siakad-secondary dark:text-gray-400">Tarif yang berlaku hari ini.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full table-saas">
                    <thead>
                        <tr class="bg-siakad-light/30 dark:bg-gray-900">
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Pegawai</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Nominal per hari</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Berlaku mulai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-siakad-light dark:divide-gray-700">
                        @forelse($tarifAktif as $tarif)
                            <tr>
                                <td class="px-5 py-3 text-sm text-siakad-dark dark:text-white">{{ $tarif->user?->name ?? '-' }}</td>
                                <td class="px-5 py-3 text-right text-sm text-siakad-dark dark:text-white">Rp{{ number_format((float) $tarif->nominal, 2, ',', '.') }}</td>
                                <td class="px-5 py-3 text-center text-sm text-siakad-secondary dark:text-gray-400">{{ $tarif->berlaku_mulai->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-8 text-center text-sm text-siakad-secondary dark:text-gray-400">Belum ada tarif aktif.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="card-saas mt-6 overflow-hidden dark:bg-gray-800">
        <div class="border-b border-siakad-light px-5 py-4 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-siakad-dark dark:text-white">Riwayat tarif</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full table-saas">
                <thead>
                    <tr class="bg-siakad-light/30 dark:bg-gray-900">
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Pegawai</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Nominal per hari</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Berlaku mulai</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Berlaku sampai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-siakad-light dark:divide-gray-700">
                    @forelse($tarifRiwayat as $tarif)
                        <tr>
                            <td class="px-5 py-3 text-sm text-siakad-dark dark:text-white">{{ $tarif->user?->name ?? '-' }}</td>
                            <td class="px-5 py-3 text-right text-sm text-siakad-dark dark:text-white">Rp{{ number_format((float) $tarif->nominal, 2, ',', '.') }}</td>
                            <td class="px-5 py-3 text-center text-sm text-siakad-secondary dark:text-gray-400">{{ $tarif->berlaku_mulai->format('d/m/Y') }}</td>
                            <td class="px-5 py-3 text-center text-sm text-siakad-secondary dark:text-gray-400">{{ $tarif->berlaku_sampai?->format('d/m/Y') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-siakad-secondary dark:text-gray-400">Belum ada riwayat tarif.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-app-layout>
