<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-gray-900 bg-white dark:bg-gray-900 selection:bg-siakad-primary selection:text-white">
    <div class="min-h-screen flex">

        <!-- Left Side - Form -->
        <div class="w-full lg:w-[480px] xl:w-[560px] flex flex-col justify-center px-8 lg:px-16 relative z-10 bg-white dark:bg-gray-900">
            <!-- Mobile Logo -->
            <div class="lg:hidden absolute top-8 left-8">
                <a href="/" class="flex items-center gap-2">
                    <img src="{{ asset('asset/favicon-nobg.png') }}" alt="{{ config('app.name') }}" class="w-10 h-10 object-contain">
                    <span class="font-bold text-xl text-siakad-dark dark:text-white">{{ config('app.name') }}</span>
                </a>
            </div>

            <div class="w-full max-w-[400px] mx-auto">
                {{ $slot }}
            </div>

            <!-- Footer -->
            <div class="absolute bottom-8 left-0 right-0 text-center text-xs text-gray-400 dark:text-gray-500">
                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
            </div>
        </div>

        <!-- Right Side - Visual -->
        <div class="hidden lg:flex flex-1 relative bg-siakad-dark overflow-hidden items-center justify-center">
            <div class="absolute inset-0 bg-gradient-to-br from-siakad-dark via-siakad-800 to-gray-900"></div>
            
            <img src="{{ asset('asset/gedung-gasantara.png') }}"
                 alt=""
                 class="absolute inset-0 w-full h-full object-cover"
                 onerror="this.style.display='none'">

            <!-- Overlay gelap agar teks tetap terbaca -->
            <div class="absolute inset-0 bg-gradient-to-br from-siakad-dark/80 via-siakad-dark/60 to-gray-900/80"></div>

            <!-- Glassmorphism Card Content -->
            <div class="relative z-10 max-w-lg text-center p-12">
                <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-8 shadow-2xl relative overflow-hidden group hover:bg-white/10 transition-colors duration-500">
                    <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>

                    <div class="w-24 h-24 mx-auto mb-8 flex items-center justify-center transform group-hover:scale-110 transition-transform duration-500">
                        <img src="{{ asset('asset/favicon-nobg.png') }}" alt="Logo {{ config('app.name') }}" class="max-w-full max-h-full object-contain drop-shadow-lg">
                    </div>

                    <h2 class="text-3xl font-bold text-white mb-4 tracking-tight">Kulim SIAKAD</h2>
                    <p class="text-siakad-100/80 text-lg leading-relaxed">
                        Platform manajemen akademik modern untuk efisiensi dan transparansi pendidikan tinggi.
                    </p>

                    <div class="mt-8 flex justify-center gap-2">
                        <div class="w-12 h-1 bg-white/30 rounded-full"></div>
                        <div class="w-2 h-1 bg-white/10 rounded-full"></div>
                        <div class="w-2 h-1 bg-white/10 rounded-full"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>