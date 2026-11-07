<x-app-layout>
    <x-slot name="header">Dashboard Staff</x-slot>

    <div class="mb-6 rounded-2xl bg-gradient-to-r from-siakad-primary to-siakad-dark p-6 text-white shadow-sm">
        <p class="text-sm font-medium text-white/80">Ringkasan Staff</p>
        <h1 class="mt-1 text-2xl font-semibold text-white">Selamat datang, {{ auth()->user()->name }}!</h1>
        <p class="mt-2 text-sm text-white/80">
            Kelola KHS dan kehadiran Anda melalui akses cepat berikut.
        </p>
    </div>

    <div class="mb-8 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
        <div class="card-saas p-5 dark:bg-gray-800">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-siakad-primary/10 dark:bg-blue-500/20">
                    <svg class="h-5 w-5 text-siakad-primary dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-siakad-dark dark:text-white">{{ $khsCount }}</p>
                    <p class="text-xs text-siakad-secondary dark:text-gray-400">KHS</p>
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
                    <p class="text-2xl font-bold text-siakad-dark dark:text-white">0</p>
                    <p class="text-xs text-siakad-secondary dark:text-gray-400">Absen</p>
                </div>
            </div>
        </div>

        <div class="card-saas p-5 dark:bg-gray-800">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-500/20">
                    <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-siakad-dark dark:text-white">0</p>
                    <p class="text-xs text-siakad-secondary dark:text-gray-400">Terkonfirmasi</p>
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
                    <p class="text-2xl font-bold text-siakad-dark dark:text-white">0</p>
                    <p class="text-xs text-siakad-secondary dark:text-gray-400">Menunggu</p>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-8">
        <h2 class="mb-4 text-lg font-semibold text-siakad-dark dark:text-white">Aksi Cepat</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <a href="{{ route('staff.khs.index') }}" class="card-saas flex items-center gap-4 p-5 transition hover:border-siakad-primary/30 hover:bg-siakad-primary/5 dark:bg-gray-800">
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-indigo-100 dark:bg-indigo-900/40">
                    <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-siakad-dark dark:text-white">KHS</p>
                    <p class="mt-1 text-sm text-siakad-secondary dark:text-gray-400">Cari mahasiswa dan lihat KHS per semester.</p>
                </div>
            </a>

            <a href="{{ route('pegawai.kehadiran.index') }}" class="card-saas flex items-center gap-4 p-5 transition hover:border-siakad-primary/30 hover:bg-siakad-primary/5 dark:bg-gray-800">
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/40">
                    <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-siakad-dark dark:text-white">Absen</p>
                    <p class="mt-1 text-sm text-siakad-secondary dark:text-gray-400">Catat presensi masuk dan keluar hari ini.</p>
                </div>
            </a>
        </div>
    </div>
</x-app-layout>
