<x-app-layout>
    <x-slot name="header">Dashboard Bendahara</x-slot>

    <div class="mb-6 rounded-2xl bg-gradient-to-r from-siakad-primary to-siakad-dark p-6 text-white shadow-sm">
        <p class="text-sm font-medium text-white/80">Ringkasan Keuangan</p>
        <h1 class="mt-1 text-2xl font-semibold text-white">Selamat datang, {{ auth()->user()->name }}!</h1>
        <p class="mt-2 text-sm text-white/80">
            Rekap honorarium dan kehadiran mengajar {{ now()->locale('id')->translatedFormat('F Y') }}.
        </p>
    </div>

    <div class="mb-8 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
        <div class="card-saas p-5 dark:bg-gray-800">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-siakad-primary/10 dark:bg-blue-500/20">
                    <svg class="h-5 w-5 text-siakad-primary dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-siakad-dark dark:text-white">{{ number_format($dosenCount, 0, ',', '.') }}</p>
                    <p class="text-xs text-siakad-secondary dark:text-gray-400">Jumlah Dosen</p>
                </div>
            </div>
        </div>

        <div class="card-saas p-5 dark:bg-gray-800">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-siakad-secondary/10 dark:bg-gray-700/50">
                    <svg class="h-5 w-5 text-siakad-secondary dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-siakad-dark dark:text-white">{{ number_format($attendanceCount, 0, ',', '.') }}</p>
                    <p class="text-xs text-siakad-secondary dark:text-gray-400">Kehadiran</p>
                </div>
            </div>
        </div>

        <div class="card-saas p-5 dark:bg-gray-800">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-500/20">
                    <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-10V4m0 16v-2M5 12h2m10 0h2"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xl font-bold text-siakad-dark dark:text-white">Rp{{ number_format((float) $paidAmount, 0, ',', '.') }}</p>
                    <p class="text-xs text-siakad-secondary dark:text-gray-400">Dibayar</p>
                </div>
            </div>
        </div>

        <div class="card-saas p-5 dark:bg-gray-800">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-red-100 dark:bg-red-500/20">
                    <svg class="h-5 w-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xl font-bold text-siakad-dark dark:text-white">Rp{{ number_format((float) $expenseAmount, 0, ',', '.') }}</p>
                    <p class="text-xs text-siakad-secondary dark:text-gray-400">Pengeluaran</p>
                </div>
            </div>
        </div>

        <div class="card-saas p-5 dark:bg-gray-800">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-500/20">
                    <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-siakad-dark dark:text-white">{{ number_format($draftCount, 0, ',', '.') }}</p>
                    <p class="text-xs text-siakad-secondary dark:text-gray-400">Menunggu</p>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-8">
        <h2 class="mb-4 text-lg font-semibold text-siakad-dark dark:text-white">Grafik Ringkasan</h2>
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <section class="card-saas p-5 dark:bg-gray-800">
                <h3 class="mb-1 font-semibold text-siakad-dark dark:text-white">Honorarium enam bulan terakhir</h3>
                <p class="mb-4 text-sm text-siakad-secondary dark:text-gray-400">Perbandingan rekap yang sudah dibayar dan draft.</p>
                <div class="relative h-72">
                    <canvas id="honorariumChart" aria-label="Grafik honorarium enam bulan terakhir" role="img"></canvas>
                </div>
            </section>

            <section class="card-saas p-5 dark:bg-gray-800">
                <h3 class="mb-1 font-semibold text-siakad-dark dark:text-white">Status verifikasi kehadiran</h3>
                <p class="mb-4 text-sm text-siakad-secondary dark:text-gray-400">Rekap kehadiran mengajar enam bulan terakhir.</p>
                <div class="relative mx-auto h-72 max-w-sm">
                    <canvas id="attendanceChart" aria-label="Grafik status verifikasi kehadiran mengajar" role="img"></canvas>
                </div>
            </section>
        </div>
    </div>

    <div class="mb-8">
        <h2 class="mb-4 text-lg font-semibold text-siakad-dark dark:text-white">Aksi Cepat</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <a href="{{ route('admin.honorarium.index') }}" class="card-saas flex items-center gap-4 p-5 transition hover:border-siakad-primary/30 hover:bg-siakad-primary/5 dark:bg-gray-800">
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/40">
                    <svg class="h-6 w-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-10V4m0 16v-2M5 12h2m10 0h2"></path>
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-siakad-dark dark:text-white">Honorarium</p>
                    <p class="mt-1 text-sm text-siakad-secondary dark:text-gray-400">Kelola rekap dan pembayaran honorarium.</p>
                </div>
            </a>

            <a href="{{ route('dosen.kehadiran.index') }}" class="card-saas flex items-center gap-4 p-5 transition hover:border-siakad-primary/30 hover:bg-siakad-primary/5 dark:bg-gray-800">
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/40">
                    <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-siakad-dark dark:text-white">Absen</p>
                    <p class="mt-1 text-sm text-siakad-secondary dark:text-gray-400">Catat absensi masuk dan keluar hari ini.</p>
                </div>
            </a>
        </div>
    </div>

    <section class="card-saas flex flex-col gap-4 p-5 dark:bg-gray-800 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="font-semibold text-siakad-dark dark:text-white">Kelola honorarium</h2>
            <p class="mt-1 text-sm text-siakad-secondary dark:text-gray-400">Lihat rekap, ekspor laporan, dan proses pembayaran honorarium.</p>
        </div>
        <a href="{{ route('admin.honorarium.index') }}" class="btn-primary-saas rounded-lg px-4 py-2 text-center text-sm font-medium">
            Buka Honorarium
        </a>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const paymentChart = new Chart(document.getElementById('honorariumChart'), {
            type: 'bar',
            data: {
                labels: {{ Illuminate\Support\Js::from($paymentChart['labels']) }},
                datasets: [
                    {
                        label: 'Dibayar',
                        data: {{ Illuminate\Support\Js::from($paymentChart['paid']) }},
                        backgroundColor: '#10b981',
                        borderRadius: 6,
                    },
                    {
                        label: 'Draft',
                        data: {{ Illuminate\Support\Js::from($paymentChart['draft']) }},
                        backgroundColor: '#f59e0b',
                        borderRadius: 6,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: value => `Rp${new Intl.NumberFormat('id-ID').format(value)}`,
                        },
                    },
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: context => `${context.dataset.label}: Rp${new Intl.NumberFormat('id-ID').format(context.raw)}`,
                        },
                    },
                },
            },
        });

        new Chart(document.getElementById('attendanceChart'), {
            type: 'doughnut',
            data: {
                labels: {{ Illuminate\Support\Js::from($attendanceChart['labels']) }},
                datasets: [{
                    data: {{ Illuminate\Support\Js::from($attendanceChart['values']) }},
                    backgroundColor: ['#f59e0b', '#10b981', '#ef4444'],
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: context => `${context.label}: ${context.raw}`,
                        },
                    },
                },
            },
        });
    </script>
</x-app-layout>
