<x-app-layout>
    <x-slot name="header">Absen</x-slot>

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

    @php
        $sudahMasuk = $kehadiranHariIni !== null;
        $sudahKeluar = $kehadiranHariIni?->jam_keluar !== null;
    @endphp

    <div class="card-saas mb-6 overflow-hidden dark:bg-gray-800">
        <div class="flex flex-col gap-3 border-b border-siakad-light px-5 py-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-siakad-dark dark:text-white">Status absensi hari ini</h2>
                <p class="text-sm text-siakad-secondary dark:text-gray-400">{{ now()->locale('id')->isoFormat('dddd, D MMMM YYYY') }}</p>
            </div>
            <span class="inline-flex w-fit items-center rounded-full px-3 py-1 text-sm font-medium
                {{ $sudahKeluar ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : ($sudahMasuk ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300') }}">
                {{ $sudahKeluar ? 'Selesai' : ($sudahMasuk ? 'Sudah masuk' : 'Belum absen') }}
            </span>
        </div>

        <div class="grid gap-4 p-5 sm:grid-cols-2">
            <div class="rounded-lg bg-siakad-light/30 p-4 dark:bg-gray-700/40">
                <p class="text-xs font-medium uppercase tracking-wide text-siakad-secondary dark:text-gray-400">Absen masuk</p>
                <p class="mt-1 text-xl font-semibold text-siakad-dark dark:text-white">
                    {{ $kehadiranHariIni?->jam_masuk ? substr($kehadiranHariIni->jam_masuk, 0, 5) : '-' }}
                </p>
            </div>
            <div class="rounded-lg bg-siakad-light/30 p-4 dark:bg-gray-700/40">
                <p class="text-xs font-medium uppercase tracking-wide text-siakad-secondary dark:text-gray-400">Absen keluar</p>
                <p class="mt-1 text-xl font-semibold text-siakad-dark dark:text-white">
                    {{ $kehadiranHariIni?->jam_keluar ? substr($kehadiranHariIni->jam_keluar, 0, 5) : '-' }}
                </p>
            </div>
        </div>
    </div>

    @if(! $sudahMasuk)
        <div class="card-saas mb-6 p-5 dark:bg-gray-800">
            <h2 class="mb-1 text-lg font-semibold text-siakad-dark dark:text-white">Absen Masuk</h2>
            <p class="mb-5 text-sm text-siakad-secondary dark:text-gray-400">Izinkan akses lokasi untuk mencatat absensi.</p>
            <form action="{{ route('pegawai.kehadiran.masuk') }}" method="POST" class="space-y-4" onsubmit="return captureAttendanceGps(event, this)">
                @csrf
                <input type="hidden" name="latitude">
                <input type="hidden" name="longitude">
                <p data-gps-status role="status" aria-live="polite" class="text-xs text-siakad-secondary dark:text-gray-400">
                    Lokasi GPS akan diperiksa sebelum absensi disimpan. GPS memerlukan HTTPS dan izin lokasi browser.
                </p>
                <div>
                    <label for="keterangan" class="mb-1 block text-xs font-semibold text-siakad-dark dark:text-gray-300">Keterangan (opsional)</label>
                    <textarea id="keterangan" name="keterangan" rows="3" maxlength="500" class="input-saas w-full text-sm dark:bg-gray-900 dark:border-gray-700 dark:text-white" placeholder="Catatan absensi...">{{ old('keterangan') }}</textarea>
                    @error('keterangan')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="btn-primary-saas min-h-[44px] rounded-lg px-5 py-2.5 text-sm font-semibold">Absen Masuk</button>
            </form>
        </div>
    @elseif(! $sudahKeluar)
        <div class="card-saas mb-6 flex flex-col gap-4 p-5 dark:bg-gray-800 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-siakad-dark dark:text-white">Absen Keluar</h2>
                <p class="text-sm text-siakad-secondary dark:text-gray-400">Jam masuk tercatat pukul {{ substr($kehadiranHariIni->jam_masuk, 0, 5) }}.</p>
            </div>
            <form action="{{ route('pegawai.kehadiran.keluar') }}" method="POST">
                @csrf
                <button type="submit" class="min-h-[44px] rounded-lg bg-siakad-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-siakad-primary/90">Absen Keluar</button>
            </form>
        </div>
    @endif

    <div class="card-saas overflow-hidden dark:bg-gray-800">
        <div class="border-b border-siakad-light px-5 py-4 dark:border-gray-700">
            <h2 class="font-semibold text-siakad-dark dark:text-white">Riwayat Absensi</h2>
            <p class="mt-1 text-sm text-siakad-secondary dark:text-gray-400">31 absensi terakhir</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full table-saas">
                <thead>
                    <tr class="bg-siakad-light/30 dark:bg-gray-900">
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Tanggal</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Jam masuk</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Jam keluar</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-siakad-secondary dark:text-gray-400">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-siakad-light dark:divide-gray-700">
                    @forelse($riwayat as $kehadiran)
                        <tr>
                            <td class="px-5 py-3 text-sm text-siakad-dark dark:text-white">{{ $kehadiran->tanggal->locale('id')->isoFormat('D MMM YYYY') }}</td>
                            <td class="px-5 py-3 text-center text-sm text-siakad-secondary dark:text-gray-400">{{ $kehadiran->jam_masuk ? substr($kehadiran->jam_masuk, 0, 5) : '-' }}</td>
                            <td class="px-5 py-3 text-center text-sm text-siakad-secondary dark:text-gray-400">{{ $kehadiran->jam_keluar ? substr($kehadiran->jam_keluar, 0, 5) : '-' }}</td>
                            <td class="px-5 py-3 text-center">
                                <span class="rounded-full bg-{{ $kehadiran->status_color }}-100 px-2.5 py-1 text-xs font-medium text-{{ $kehadiran->status_color }}-700 dark:bg-{{ $kehadiran->status_color }}-900/50 dark:text-{{ $kehadiran->status_color }}-400">{{ $kehadiran->status_label }}</span>
                            </td>
                            <td class="px-5 py-3 text-sm text-siakad-secondary dark:text-gray-400">{{ $kehadiran->keterangan ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-sm text-siakad-secondary dark:text-gray-400">Belum ada riwayat absensi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function captureAttendanceGps(event, form) {
            if (form.dataset.gpsReady === 'true') {
                return true;
            }

            event.preventDefault();
            const status = form.querySelector('[data-gps-status]');
            const submitButton = form.querySelector('button[type="submit"]');
            if (!navigator.geolocation) {
                status.textContent = 'Browser ini tidak mendukung GPS. Gunakan browser lain untuk presensi.';
                return false;
            }

            status.textContent = 'Mengambil lokasi GPS...';
            submitButton.disabled = true;
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    form.elements.latitude.value = position.coords.latitude;
                    form.elements.longitude.value = position.coords.longitude;
                    form.dataset.gpsReady = 'true';
                    form.requestSubmit();
                },
                (error) => {
                    const messages = {
                        1: 'Izin lokasi ditolak. Aktifkan izin lokasi pada browser untuk melanjutkan.',
                        2: 'Lokasi tidak dapat ditemukan. Pastikan GPS perangkat aktif lalu coba lagi.',
                        3: 'Pengambilan lokasi terlalu lama. Silakan coba lagi.',
                    };
                    status.textContent = messages[error.code] || 'Gagal mengambil lokasi GPS. Silakan coba lagi.';
                    submitButton.disabled = false;
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );

            return false;
        }
    </script>
</x-app-layout>
