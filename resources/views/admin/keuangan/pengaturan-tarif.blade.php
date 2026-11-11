<x-app-layout>
    <x-slot name="header">Pengaturan Tarif</x-slot>

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

    <section class="card-saas mb-6 p-5 dark:bg-gray-800">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-siakad-dark dark:text-white">Tarif Dosen</h2>
            @if($tarifDosenAktif->isNotEmpty())
                @foreach($tarifDosenAktif as $tarif)
                    <p class="text-sm text-siakad-secondary dark:text-gray-400">
                        Aktif sejak {{ $tarif->berlaku_mulai->format('d/m/Y') }}:
                        Rp{{ number_format((float) $tarif->nominal, 2, ',', '.') }}/{{ $tarif->basis_tarif === 'per_sks' ? 'SKS' : 'kehadiran' }}.
                    </p>
                @endforeach
            @else
                <p class="text-sm text-amber-700 dark:text-amber-300">Belum ada tarif dosen yang aktif.</p>
            @endif
        </div>
        <form method="POST" action="{{ route('admin.pengaturan-tarif.dosen.store') }}" class="grid gap-3 md:grid-cols-4">
            @csrf
            <label class="block text-sm text-siakad-dark dark:text-gray-300">
                Basis tarif
                <select name="basis_tarif" required class="input-saas mt-1 w-full rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                    <option value="per_sks" @selected(old('basis_tarif') === 'per_sks')>Per SKS</option>
                    <option value="per_kehadiran" @selected(old('basis_tarif') === 'per_kehadiran')>Per kehadiran</option>
                </select>
            </label>
            <label class="block text-sm text-siakad-dark dark:text-gray-300">
                Nominal tarif
                <input name="nominal" type="number" min="0.01" step="0.01" value="{{ old('nominal') }}" required class="input-saas mt-1 w-full rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
            </label>
            <label class="block text-sm text-siakad-dark dark:text-gray-300">
                Berlaku mulai
                <input name="berlaku_mulai" type="date" value="{{ old('berlaku_mulai') }}" required class="input-saas mt-1 w-full rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
            </label>
            <div class="flex items-end">
                <button class="btn-primary-saas rounded-lg px-4 py-2">Simpan tarif dosen</button>
            </div>
        </form>

        @if($tarifDosenRiwayat->isNotEmpty())
            <div class="mt-5 overflow-x-auto">
                <h3 class="mb-2 text-sm font-semibold text-siakad-dark dark:text-white">Riwayat tarif dosen</h3>
                <table class="w-full table-saas">
                    <thead>
                        <tr class="bg-siakad-light/30 dark:bg-gray-900">
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Basis</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Nominal</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Berlaku</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-siakad-light dark:divide-gray-700">
                        @foreach($tarifDosenRiwayat as $tarif)
                            <tr>
                                <td class="px-4 py-2 text-sm text-siakad-dark dark:text-white">{{ $tarif->basis_tarif === 'per_sks' ? 'Per SKS' : 'Per kehadiran' }}</td>
                                <td class="px-4 py-2 text-right text-sm text-siakad-dark dark:text-white">Rp{{ number_format((float) $tarif->nominal, 2, ',', '.') }}</td>
                                <td class="px-4 py-2 text-center text-sm text-siakad-secondary dark:text-gray-400">{{ $tarif->berlaku_mulai->format('d/m/Y') }} – {{ $tarif->berlaku_sampai?->format('d/m/Y') ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card-saas mb-6 p-5 dark:bg-gray-800">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-siakad-dark dark:text-white">Tarif Pegawai</h2>
            <p class="text-sm text-siakad-secondary dark:text-gray-400">Nominal tarif berlaku untuk satu bulan dan dibayarkan satu kali jika pegawai memiliki kehadiran yang selesai pada bulan tersebut. Jumlah hari hadir hanya untuk rekap. Tarif per user diutamakan; jika tidak tersedia, tarif berdasarkan role digunakan.</p>
        </div>
        <div class="grid gap-6 xl:grid-cols-2">
            <form method="POST" action="{{ route('admin.pengaturan-tarif.pegawai.store') }}" class="space-y-3 rounded-lg border border-siakad-light p-4 dark:border-gray-700">
                @csrf
                <input type="hidden" name="target_type" value="role">
                <h3 class="font-semibold text-siakad-dark dark:text-white">Atur tarif per role</h3>
                <label class="block text-sm text-siakad-dark dark:text-gray-300">
                    Role pegawai
                    <select name="role" required class="input-saas mt-1 w-full rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                        <option value="">Pilih role</option>
                        @foreach($roleLabels as $role => $label)
                            <option value="{{ $role }}" @selected(old('role') === $role)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm text-siakad-dark dark:text-gray-300">
                    Nominal per bulan
                    <input name="nominal" type="number" min="0.01" step="0.01" value="{{ old('nominal') }}" required class="input-saas mt-1 w-full rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                </label>
                <label class="block text-sm text-siakad-dark dark:text-gray-300">
                    Berlaku mulai
                    <input name="berlaku_mulai" type="date" value="{{ old('berlaku_mulai') }}" required class="input-saas mt-1 w-full rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                </label>
                <button class="btn-primary-saas rounded-lg px-4 py-2">Simpan tarif role</button>
            </form>

            <form method="POST" action="{{ route('admin.pengaturan-tarif.pegawai.store') }}" class="space-y-3 rounded-lg border border-siakad-light p-4 dark:border-gray-700">
                @csrf
                <input type="hidden" name="target_type" value="user">
                <h3 class="font-semibold text-siakad-dark dark:text-white">Atur tarif per user</h3>
                <label class="block text-sm text-siakad-dark dark:text-gray-300">
                    Pegawai
                    <select name="user_id" required class="input-saas mt-1 w-full rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                        <option value="">Pilih pegawai</option>
                        @foreach($pegawaiList as $pegawai)
                            <option value="{{ $pegawai->id }}" @selected(old('user_id') == $pegawai->id)>{{ $pegawai->name }} ({{ $pegawai->email }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm text-siakad-dark dark:text-gray-300">
                    Nominal per bulan
                    <input name="nominal" type="number" min="0.01" step="0.01" value="{{ old('nominal') }}" required class="input-saas mt-1 w-full rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                </label>
                <label class="block text-sm text-siakad-dark dark:text-gray-300">
                    Berlaku mulai
                    <input name="berlaku_mulai" type="date" value="{{ old('berlaku_mulai') }}" required class="input-saas mt-1 w-full rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                </label>
                <button type="submit" @disabled($pegawaiList->isEmpty()) class="btn-primary-saas rounded-lg px-4 py-2 disabled:cursor-not-allowed disabled:opacity-50">Simpan tarif user</button>
            </form>
        </div>
        @if($pegawaiList->isEmpty())
            <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">Belum ada pegawai dengan role superadmin, bendahara, atau staff.</p>
        @endif
    </section>

    <section class="card-saas mb-6 overflow-hidden dark:bg-gray-800">
        <div class="border-b border-siakad-light px-5 py-4 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-siakad-dark dark:text-white">Tarif pegawai aktif</h2>
            <p class="text-sm text-siakad-secondary dark:text-gray-400">Tarif yang berlaku hari ini.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full table-saas">
                <thead>
                    <tr class="bg-siakad-light/30 dark:bg-gray-900">
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Berlaku untuk</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Nominal per bulan</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Berlaku mulai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-siakad-light dark:divide-gray-700">
                    @forelse($tarifAktif as $tarif)
                        <tr>
                            <td class="px-5 py-3 text-sm text-siakad-dark dark:text-white">{{ $tarif->user_id ? $tarif->user?->name : ($roleLabels[$tarif->role] ?? $tarif->role) }}</td>
                            <td class="px-5 py-3 text-right text-sm text-siakad-dark dark:text-white">Rp{{ number_format((float) $tarif->nominal, 2, ',', '.') }}</td>
                            <td class="px-5 py-3 text-center text-sm text-siakad-secondary dark:text-gray-400">{{ $tarif->berlaku_mulai->format('d/m/Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-8 text-center text-sm text-siakad-secondary dark:text-gray-400">Belum ada tarif pegawai aktif.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card-saas overflow-hidden dark:bg-gray-800">
        <div class="border-b border-siakad-light px-5 py-4 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-siakad-dark dark:text-white">Riwayat tarif pegawai</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full table-saas">
                <thead>
                    <tr class="bg-siakad-light/30 dark:bg-gray-900">
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Berlaku untuk</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Nominal per bulan</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Berlaku mulai</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Berlaku sampai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-siakad-light dark:divide-gray-700">
                    @forelse($tarifRiwayat as $tarif)
                        <tr>
                            <td class="px-5 py-3 text-sm text-siakad-dark dark:text-white">{{ $tarif->user_id ? $tarif->user?->name : ($roleLabels[$tarif->role] ?? $tarif->role) }}</td>
                            <td class="px-5 py-3 text-right text-sm text-siakad-dark dark:text-white">Rp{{ number_format((float) $tarif->nominal, 2, ',', '.') }}</td>
                            <td class="px-5 py-3 text-center text-sm text-siakad-secondary dark:text-gray-400">{{ $tarif->berlaku_mulai->format('d/m/Y') }}</td>
                            <td class="px-5 py-3 text-center text-sm text-siakad-secondary dark:text-gray-400">{{ $tarif->berlaku_sampai?->format('d/m/Y') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-siakad-secondary dark:text-gray-400">Belum ada riwayat tarif pegawai.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-app-layout>
