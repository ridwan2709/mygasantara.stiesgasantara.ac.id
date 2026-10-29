<x-app-layout>
    <x-slot name="header">Absen</x-slot>

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Status hari ini</p>
                <p class="mt-3 text-lg font-semibold text-slate-900">
                    @php
                        $statusLabel = 'Belum absen';
                        if ($todayAttendance) {
                            if ($todayAttendance->jam_masuk && ! $todayAttendance->jam_keluar) {
                                $statusLabel = 'Sudah masuk';
                            } elseif ($todayAttendance->jam_keluar) {
                                $statusLabel = 'Selesai';
                            }
                        }
                    @endphp
                    {{ $statusLabel }}
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Jam masuk</p>
                <p class="mt-3 text-lg font-semibold text-slate-900">
                    {{ $todayAttendance?->jam_masuk ? substr($todayAttendance->jam_masuk, 0, 5) : '-' }}
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Jam keluar</p>
                <p class="mt-3 text-lg font-semibold text-slate-900">
                    {{ $todayAttendance?->jam_keluar ? substr($todayAttendance->jam_keluar, 0, 5) : '-' }}
                </p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-slate-900">Absen Masuk</h2>

                <form action="{{ route('admin.kehadiran-pegawai.masuk') }}" method="POST" onsubmit="return captureAttendanceGps(event, this)" class="space-y-4">
                    @csrf
                    <input type="hidden" name="latitude">
                    <input type="hidden" name="longitude">

                    <p data-gps-status role="status" aria-live="polite" class="text-sm text-slate-600">
                        Lokasi GPS akan diperiksa sebelum presensi disimpan.
                    </p>

                    <div>
                        <label for="keterangan_masuk" class="mb-1 block text-sm font-medium text-slate-700">Keterangan (opsional)</label>
                        <textarea id="keterangan_masuk" name="keterangan" rows="4" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100" placeholder="Catatan singkat..."></textarea>
                    </div>

                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-700">
                        Absen Masuk
                    </button>
                </form>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-slate-900">Absen Keluar</h2>

                <form action="{{ route('admin.kehadiran-pegawai.keluar') }}" method="POST" class="space-y-4">
                    @csrf
                    <p class="text-sm text-slate-600">
                        Hanya bisa dilakukan setelah absen masuk hari ini dan sebelum jam keluar dicatat.
                    </p>

                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                        Absen Keluar
                    </button>
                </form>
            </div>
        </div>

        <div class="card-saas overflow-hidden dark:bg-gray-800">
            <div class="border-b border-siakad-light px-6 py-4 dark:border-gray-700">
                <h3 class="font-semibold text-siakad-dark dark:text-white">Detail Kehadiran</h3>
            </div>

            <div class="overflow-x-auto">
                @php
                    $entryBadgeClasses = [
                        'slate' => 'bg-slate-100 text-slate-700 dark:bg-slate-800/50 dark:text-slate-300',
                        'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400',
                        'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-400',
                    ];
                @endphp

                <table class="hidden w-full table-saas md:table">
                    <thead>
                        <tr class="bg-siakad-light/30 dark:bg-gray-900">
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-siakad-secondary dark:text-gray-400">Tanggal</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-siakad-secondary dark:text-gray-400">Status</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wide text-siakad-secondary dark:text-gray-400">Jam Masuk</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wide text-siakad-secondary dark:text-gray-400">Jam Keluar</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-siakad-secondary dark:text-gray-400">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($history as $entry)
                            @php
                                $entryStatus = 'Belum absen';
                                $entryColor = 'slate';

                                if ($entry->jam_masuk && ! $entry->jam_keluar) {
                                    $entryStatus = 'Sudah masuk';
                                    $entryColor = 'amber';
                                } elseif ($entry->jam_keluar) {
                                    $entryStatus = 'Selesai';
                                    $entryColor = 'emerald';
                                }
                            @endphp
                            <tr class="border-b border-siakad-light/50 dark:border-gray-700/50">
                                <td class="px-5 py-3 text-sm text-siakad-dark dark:text-white">{{ $entry->tanggal->translatedFormat('d M Y') }}</td>
                                <td class="px-5 py-3 text-sm text-siakad-dark dark:text-white">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $entryBadgeClasses[$entryColor] ?? $entryBadgeClasses['slate'] }}">
                                        {{ $entryStatus }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-center text-sm text-siakad-secondary dark:text-gray-400">
                                    {{ $entry->jam_masuk ? substr($entry->jam_masuk, 0, 5) : '-' }}
                                </td>
                                <td class="px-5 py-3 text-center text-sm text-siakad-secondary dark:text-gray-400">
                                    {{ $entry->jam_keluar ? substr($entry->jam_keluar, 0, 5) : '-' }}
                                </td>
                                <td class="px-5 py-3 text-sm text-siakad-secondary dark:text-gray-400">
                                    {{ $entry->keterangan ?: '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-siakad-secondary dark:text-gray-400">Belum ada data</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="divide-y divide-siakad-light dark:divide-gray-700 md:hidden">
                    @forelse ($history as $entry)
                        @php
                            $entryStatus = 'Belum absen';
                            $entryColor = 'slate';

                            if ($entry->jam_masuk && ! $entry->jam_keluar) {
                                $entryStatus = 'Sudah masuk';
                                $entryColor = 'amber';
                            } elseif ($entry->jam_keluar) {
                                $entryStatus = 'Selesai';
                                $entryColor = 'emerald';
                            }
                        @endphp
                        <div class="p-4">
                            <div class="mb-3 flex items-start justify-between">
                                <div>
                                    <span class="text-xs text-siakad-secondary dark:text-gray-400">{{ $entry->tanggal->translatedFormat('d M Y') }}</span>
                                    <h4 class="mt-0.5 text-sm font-bold text-siakad-dark dark:text-white">{{ $entryStatus }}</h4>
                                </div>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-medium {{ $entryBadgeClasses[$entryColor] ?? $entryBadgeClasses['slate'] }}">
                                    {{ $entryStatus }}
                                </span>
                            </div>

                            <div class="rounded-lg bg-gray-50 p-3 text-xs dark:bg-gray-700/50">
                                <div class="flex items-center gap-2 text-siakad-secondary dark:text-gray-400">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span>{{ $entry->jam_masuk ? substr($entry->jam_masuk, 0, 5) : '-' }} - {{ $entry->jam_keluar ? substr($entry->jam_keluar, 0, 5) : '-' }}</span>
                                </div>
                                <div class="mt-2 text-siakad-secondary dark:text-gray-400">
                                    Keterangan: {{ $entry->keterangan ?: '-' }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-sm text-siakad-secondary dark:text-gray-400">Belum ada data</div>
                    @endforelse
                </div>
            </div>
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

            if (! navigator.geolocation) {
                status.textContent = 'Browser ini tidak mendukung GPS. Gunakan browser lain untuk absen.';
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
