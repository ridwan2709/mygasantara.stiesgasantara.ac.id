<x-app-layout>
    <x-slot name="header">
        Buat KRS Satu Angkatan
    </x-slot>

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-siakad-secondary dark:text-gray-400">
            Pilih program studi, angkatan, semester, lalu susun satu KRS untuk seluruh mahasiswa aktif dalam kelompok tersebut.
        </p>
        <a href="{{ route('admin.krs-approval.index') }}" class="text-sm font-medium text-siakad-primary hover:underline">
            Lihat daftar KRS
        </a>
    </div>

    @if(!$tahunAkademik)
    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
        Belum ada tahun akademik aktif. Aktifkan tahun akademik sebelum membuat KRS.
    </div>
    @else
    <div class="mb-6 rounded-xl bg-siakad-primary p-5 text-white">
        <p class="text-xs uppercase tracking-wider opacity-75">Tahun Akademik Aktif</p>
        <p class="mt-1 text-lg font-semibold">{{ $tahunAkademik->tahun }} - {{ $tahunAkademik->semester }}</p>
    </div>
    @endif

    <div class="card-saas mb-6 p-6 dark:bg-gray-800">
        <h2 class="mb-4 font-semibold text-siakad-dark dark:text-white">1. Tentukan Kelompok dan Semester</h2>
        <form id="cohortForm" method="GET" action="{{ route('admin.krs.create') }}" class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label for="prodi_id" class="mb-2 block text-sm font-medium text-siakad-dark dark:text-gray-300">Program Studi</label>
                <select
                    id="prodi_id"
                    name="prodi_id"
                    class="input-saas w-full rounded-lg px-4 py-2.5 text-sm dark:bg-gray-900 dark:text-white"
                    onchange="document.getElementById('angkatan').value = ''; document.getElementById('semester').value = ''; this.form.submit()"
                    @disabled(!$tahunAkademik)
                >
                    <option value="">Pilih program studi...</option>
                    @foreach($prodiList as $item)
                    <option value="{{ $item->id }}" @selected($prodi?->id === $item->id)>
                        {{ $item->nama }}@if(auth()->user()->isSuperAdmin()) - {{ $item->fakultas->nama ?? '' }}@endif
                    </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="angkatan" class="mb-2 block text-sm font-medium text-siakad-dark dark:text-gray-300">Angkatan</label>
                <select
                    id="angkatan"
                    name="angkatan"
                    class="input-saas w-full rounded-lg px-4 py-2.5 text-sm dark:bg-gray-900 dark:text-white"
                    onchange="document.getElementById('semester').value = ''; this.form.submit()"
                    @disabled(!$prodi)
                >
                    <option value="">Pilih angkatan...</option>
                    @foreach($angkatanList as $item)
                    <option value="{{ $item }}" @selected((string) request('angkatan') === (string) $item)>{{ $item }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="semester" class="mb-2 block text-sm font-medium text-siakad-dark dark:text-gray-300">Semester Mata Kuliah</label>
                <select
                    id="semester"
                    name="semester"
                    class="input-saas w-full rounded-lg px-4 py-2.5 text-sm dark:bg-gray-900 dark:text-white"
                    onchange="this.form.submit()"
                    @disabled(!$prodi || !request('angkatan'))
                >
                    <option value="">Pilih semester...</option>
                    @foreach($semesterList as $item)
                    <option value="{{ $item }}" @selected((string) request('semester') === (string) $item)>Semester {{ $item }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    @if($prodi && request('angkatan') && request('semester'))
    @if($mahasiswaCount === 0)
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
        Tidak ada mahasiswa aktif untuk kelompok yang dipilih.
    </div>
    @else
    <form action="{{ route('admin.krs.store') }}" method="POST" class="card-saas overflow-hidden dark:bg-gray-800">
        @csrf
        <input type="hidden" name="prodi_id" value="{{ $prodi->id }}">
        <input type="hidden" name="angkatan" value="{{ request('angkatan') }}">
        <input type="hidden" name="semester" value="{{ request('semester') }}">

        <div class="border-b border-siakad-light p-6 dark:border-gray-700">
            <h2 class="font-semibold text-siakad-dark dark:text-white">2. Pilih Mata Kuliah dan Kelas</h2>
            <p class="mt-1 text-sm text-siakad-secondary dark:text-gray-400">
                {{ $prodi->nama }} · Angkatan {{ request('angkatan') }} · Semester {{ request('semester') }} · {{ $mahasiswaCount }} mahasiswa aktif
            </p>
            <p class="mt-1 text-xs text-siakad-secondary dark:text-gray-400">
                KRS seluruh mahasiswa kelompok ini akan disamakan dan langsung disetujui. KRS semester aktif yang sudah ada akan diperbarui.
                Batas SKS kelompok: {{ $maxSks }} SKS.
            </p>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <label class="inline-flex items-center gap-2 text-sm font-medium text-siakad-dark dark:text-gray-200">
                    <input id="selectAllCourses" type="checkbox" class="rounded border-siakad-light text-siakad-primary focus:ring-siakad-primary">
                    Pilih semua mata kuliah
                </label>
                <div
                    id="selectedSks"
                    data-max-sks="{{ $maxSks }}"
                    class="inline-flex items-center gap-2 rounded-lg bg-siakad-primary/10 px-4 py-2 text-sm font-semibold text-siakad-primary dark:bg-blue-500/20 dark:text-blue-300"
                    aria-live="polite"
                >
                    Total SKS:
                    <span id="selectedSksValue">0</span>
                    <span>/ {{ $maxSks }} SKS</span>
                </div>
            </div>
            <p id="selectedSksMessage" class="mt-2 hidden text-sm text-red-600 dark:text-red-400" role="alert"></p>
        </div>

        <div class="divide-y divide-siakad-light dark:divide-gray-700">
            @forelse($availableKelas as $courseClasses)
            @php
                $mataKuliah = $courseClasses->first()->mataKuliah;
                $courseId = (string) $mataKuliah->id;
                $selectedCourseIds = (array) old('mata_kuliah_ids', []);
                $isSelected = in_array($courseId, array_map('strval', $selectedCourseIds), true);
                $availableSections = $courseClasses->filter(fn ($kelas) => $kelas->available_for_cohort);
                $selectedClassId = old("kelas_per_mata_kuliah.{$courseId}", $availableSections->first()?->id);
            @endphp
            <section class="p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                    <label class="flex flex-1 items-start gap-3">
                        <input
                            type="checkbox"
                            name="mata_kuliah_ids[]"
                            value="{{ $mataKuliah->id }}"
                            data-course-sks="{{ $mataKuliah->sks }}"
                            data-course-selector
                            class="mt-1 rounded border-siakad-light text-siakad-primary focus:ring-siakad-primary"
                            @disabled($availableSections->isEmpty())
                            @checked($isSelected)
                        >
                        <span>
                            <span class="block font-medium text-siakad-dark dark:text-white">
                                {{ $mataKuliah->kode_mk }} - {{ $mataKuliah->nama_mk }}
                            </span>
                            <span class="mt-1 block text-xs text-siakad-secondary dark:text-gray-400">
                                Semester {{ $mataKuliah->semester }} · {{ $mataKuliah->sks }} SKS
                            </span>
                        </span>
                    </label>

                    <div class="sm:w-72">
                        <label for="kelas-{{ $mataKuliah->id }}" class="mb-1 block text-xs font-medium text-siakad-secondary dark:text-gray-400">
                            Pilih kelas
                        </label>
                        <select
                            id="kelas-{{ $mataKuliah->id }}"
                            name="kelas_per_mata_kuliah[{{ $mataKuliah->id }}]"
                            data-course-class
                            class="input-saas w-full rounded-lg px-3 py-2 text-sm dark:bg-gray-900 dark:text-white"
                            @disabled(!$isSelected || $availableSections->isEmpty())
                        >
                            @forelse($availableSections as $kelas)
                            <option value="{{ $kelas->id }}" @selected((string) $selectedClassId === (string) $kelas->id)>
                                Kelas {{ $kelas->nama_kelas }} · {{ $kelas->dosen->user->name ?? 'Dosen belum ditentukan' }} · Tersedia {{ $kelas->cohort_remaining_seats }} kursi
                            </option>
                            @empty
                            <option value="">Tidak ada kelas dengan kapasitas cukup</option>
                            @endforelse
                        </select>
                    </div>
                </div>
            </section>
            @empty
            <p class="p-6 text-sm text-siakad-secondary dark:text-gray-400">
                Tidak ada mata kuliah atau kelas tersedia untuk semester ini.
            </p>
            @endforelse
        </div>

        @error('mata_kuliah_ids')
        <p class="px-6 pt-4 text-sm text-red-600">{{ $message }}</p>
        @enderror
        @error('kelas_per_mata_kuliah')
        <p class="px-6 pt-4 text-sm text-red-600">{{ $message }}</p>
        @enderror

        <div class="flex flex-col gap-3 border-t border-siakad-light px-6 py-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-siakad-secondary dark:text-gray-400">Pemilihan kelas yang sama diterapkan ke {{ $mahasiswaCount }} mahasiswa.</p>
            <button id="saveKrsButton" type="submit" class="btn-primary-saas rounded-lg px-5 py-2.5 text-sm font-medium">
                Simpan KRS untuk Semua Mahasiswa
            </button>
        </div>
    </form>
    @endif
    @endif

    @if($prodi && request('angkatan') && request('semester') && $mahasiswaCount > 0)
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAll = document.getElementById('selectAllCourses');
            const courseSelectors = Array.from(document.querySelectorAll('[data-course-selector]'));
            const classSelectors = Array.from(document.querySelectorAll('[data-course-class]'));
            const totalSksValue = document.getElementById('selectedSksValue');
            const message = document.getElementById('selectedSksMessage');
            const maxSks = Number(document.getElementById('selectedSks').dataset.maxSks);
            const saveButton = document.getElementById('saveKrsButton');

            function updateTotalSks() {
                const selected = courseSelectors.filter((checkbox) => checkbox.checked);
                const totalSks = selected.reduce((total, checkbox) => total + Number(checkbox.dataset.courseSks), 0);
                const exceedsLimit = totalSks > maxSks;

                totalSksValue.textContent = totalSks;
                message.textContent = exceedsLimit
                    ? `Jumlah SKS melebihi batas kelompok, yaitu ${maxSks} SKS.`
                    : '';
                message.classList.toggle('hidden', !exceedsLimit);
                saveButton.disabled = exceedsLimit || selected.length === 0;
                saveButton.classList.toggle('opacity-50', saveButton.disabled);
                saveButton.classList.toggle('cursor-not-allowed', saveButton.disabled);
                selectAll.checked = courseSelectors.length > 0 && courseSelectors.every((checkbox) => checkbox.disabled || checkbox.checked);
                selectAll.indeterminate = courseSelectors.some((checkbox) => checkbox.checked)
                    && courseSelectors.some((checkbox) => !checkbox.disabled && !checkbox.checked);
            }

            courseSelectors.forEach((checkbox) => {
                checkbox.addEventListener('change', function () {
                    const classSelector = checkbox.closest('section').querySelector('[data-course-class]');
                    classSelector.disabled = !checkbox.checked;
                    updateTotalSks();
                });
            });

            classSelectors.forEach((select) => {
                select.addEventListener('change', updateTotalSks);
            });

            selectAll.addEventListener('change', function () {
                courseSelectors.forEach((checkbox) => {
                    if (checkbox.disabled) {
                        return;
                    }

                    checkbox.checked = selectAll.checked;
                    checkbox.closest('section').querySelector('[data-course-class]').disabled = !checkbox.checked;
                });

                updateTotalSks();
            });

            updateTotalSks();
        });
    </script>
    @endif
</x-app-layout>
