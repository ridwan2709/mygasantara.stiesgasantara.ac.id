<x-app-layout>
    <x-slot name="header">Pengaturan GPS Kehadiran Dosen</x-slot>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-300">
            <ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="card-saas max-w-6xl p-6 dark:bg-gray-800">
        <h2 class="mb-2 text-lg font-semibold text-siakad-dark dark:text-white">Lokasi kampus untuk presensi</h2>
        <p class="mb-5 text-sm text-siakad-secondary dark:text-gray-400">
            Klik peta atau geser penanda untuk menentukan titik kampus. Lingkaran menunjukkan area yang diizinkan untuk presensi. GPS browser memerlukan HTTPS (kecuali localhost) dan izin lokasi.
        </p>

        <form method="POST" action="{{ route('admin.kehadiran-gps.update') }}" class="space-y-5">
            @csrf
            @method('PUT')
            <div class="grid gap-5 lg:grid-cols-3">
                <div class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                        <label class="block text-sm font-medium text-siakad-dark dark:text-gray-300">
                            Latitude
                            <input name="latitude" type="number" step="any" min="-90" max="90" required value="{{ old('latitude', $pengaturanGps?->latitude) }}" class="input-saas mt-1 w-full rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                        </label>
                        <label class="block text-sm font-medium text-siakad-dark dark:text-gray-300">
                            Longitude
                            <input name="longitude" type="number" step="any" min="-180" max="180" required value="{{ old('longitude', $pengaturanGps?->longitude) }}" class="input-saas mt-1 w-full rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                        </label>
                    </div>
                    <label class="block text-sm font-medium text-siakad-dark dark:text-gray-300">
                        Radius presensi (meter)
                        <input name="radius_meter" type="number" min="10" max="5000" step="1" required value="{{ old('radius_meter', $pengaturanGps?->radius_meter ?? 100) }}" class="input-saas mt-1 w-full rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                        <span class="mt-1 block text-xs font-normal text-siakad-secondary dark:text-gray-400">Minimal 10 meter dan maksimal 5.000 meter.</span>
                    </label>
                    <p class="rounded-lg bg-siakad-light/30 p-3 text-sm text-siakad-secondary dark:bg-gray-700/50 dark:text-gray-300">
                        Radius yang ditampilkan: <strong id="radiusPreview" class="text-siakad-dark dark:text-white">100 meter</strong>
                    </p>
                    <p id="campusGpsStatus" role="status" aria-live="polite" class="text-sm text-siakad-secondary dark:text-gray-400"></p>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" onclick="useCurrentLocation()" class="btn-ghost-saas rounded-lg border border-siakad-light px-4 py-2 dark:border-gray-700 dark:text-white">Gunakan lokasi perangkat</button>
                        <button type="submit" class="btn-primary-saas rounded-lg px-4 py-2">Simpan pengaturan</button>
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <div id="campusMap" class="h-[420px] w-full overflow-hidden rounded-xl border border-siakad-light bg-siakad-light/30 dark:border-gray-700 dark:bg-gray-900" style="height: 420px; min-height: 320px;" aria-label="Peta lokasi dan radius presensi kampus"></div>
                    <p class="mt-2 text-xs text-siakad-secondary dark:text-gray-400">Peta © OpenStreetMap. Klik peta atau geser penanda untuk memilih lokasi.</p>
                    <p id="campusMapStatus" role="status" aria-live="polite" class="mt-1 text-xs text-amber-700 dark:text-amber-300"></p>
                </div>
            </div>
        </form>
    </section>

    @push('scripts')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            const latitudeInput = document.querySelector('[name="latitude"]');
            const longitudeInput = document.querySelector('[name="longitude"]');
            const radiusInput = document.querySelector('[name="radius_meter"]');
            const radiusPreview = document.getElementById('radiusPreview');
            const campusGpsStatus = document.getElementById('campusGpsStatus');
            const campusMapStatus = document.getElementById('campusMapStatus');
            const campusMap = L.map('campusMap').setView([-2.5, 118], 5);
            let campusMarker = null;
            let campusRadius = null;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>',
            }).addTo(campusMap);

            function readCoordinates() {
                const latitude = Number(latitudeInput.value);
                const longitude = Number(longitudeInput.value);

                if (latitudeInput.value === '' || longitudeInput.value === '' || !Number.isFinite(latitude) || !Number.isFinite(longitude) || latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) {
                    return null;
                }

                return [latitude, longitude];
            }

            function readRadius() {
                const radius = Number(radiusInput.value);
                return Number.isInteger(radius) && radius >= 10 && radius <= 5000 ? radius : null;
            }

            function updateRadiusPreview() {
                const radius = readRadius();
                radiusPreview.textContent = radius === null ? 'Masukkan radius 10–5.000 meter' : `${radius.toLocaleString('id-ID')} meter`;
                if (campusRadius && radius !== null) {
                    campusRadius.setRadius(radius);
                }
            }

            function updateMapLocation(centerMap = false) {
                const coordinates = readCoordinates();
                if (!coordinates) {
                    return;
                }

                if (!campusMarker) {
                    campusMarker = L.marker(coordinates, { draggable: true }).addTo(campusMap);
                    campusMarker.on('dragend', () => setCoordinates(campusMarker.getLatLng()));
                    campusRadius = L.circle(coordinates, {
                        radius: readRadius() ?? 100,
                        color: '#234C6A',
                        fillColor: '#3b82f6',
                        fillOpacity: 0.18,
                        weight: 2,
                    }).addTo(campusMap);
                    centerMap = true;
                } else {
                    campusMarker.setLatLng(coordinates);
                    campusRadius.setLatLng(coordinates);
                }

                if (centerMap) {
                    campusMap.setView(coordinates, 17);
                }
                updateRadiusPreview();
            }

            function setCoordinates(location) {
                latitudeInput.value = location.lat.toFixed(7);
                longitudeInput.value = location.lng.toFixed(7);
                updateMapLocation();
            }

            campusMap.on('click', (event) => setCoordinates(event.latlng));
            latitudeInput.addEventListener('input', () => updateMapLocation());
            longitudeInput.addEventListener('input', () => updateMapLocation());
            radiusInput.addEventListener('input', updateRadiusPreview);
            updateRadiusPreview();
            updateMapLocation(true);
            window.addEventListener('load', () => campusMap.invalidateSize());
            window.setTimeout(() => campusMap.invalidateSize(), 250);

            function useCurrentLocation() {
                if (!navigator.geolocation) {
                    campusGpsStatus.textContent = 'Browser ini tidak mendukung GPS.';
                    return;
                }

                campusGpsStatus.textContent = 'Mengambil lokasi perangkat...';
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        setCoordinates(position.coords);
                        campusMap.setView([position.coords.latitude, position.coords.longitude], 17);
                        campusGpsStatus.textContent = 'Koordinat berhasil diisi. Pastikan perangkat berada di lokasi kampus, lalu simpan.';
                    },
                    (error) => {
                        const messages = {
                            1: 'Izin lokasi ditolak. Aktifkan izin lokasi pada browser untuk melanjutkan.',
                            2: 'Lokasi tidak dapat ditemukan. Pastikan GPS perangkat aktif lalu coba lagi.',
                            3: 'Pengambilan lokasi terlalu lama. Silakan coba lagi.',
                        };
                        campusGpsStatus.textContent = messages[error.code] || 'Gagal mengambil lokasi GPS. Silakan coba lagi.';
                    },
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
                );
            }
        </script>
    @endpush
</x-app-layout>
