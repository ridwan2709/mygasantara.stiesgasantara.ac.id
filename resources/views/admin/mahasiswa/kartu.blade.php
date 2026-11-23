<x-app-layout>
    <x-slot name="header">Kartu Tanda Mahasiswa</x-slot>

    <div class="mb-6 flex items-center justify-between gap-3">
        <a href="{{ route('admin.mahasiswa.show', $mahasiswa) }}" class="inline-flex items-center gap-2 text-sm text-siakad-secondary hover:text-siakad-primary transition">
            Kembali ke Detail
        </a>
        <a href="{{ route('admin.mahasiswa.kartu.cetak', $mahasiswa) }}" class="btn-primary-saas px-4 py-2 rounded-lg text-sm font-medium">
            Cetak PDF
        </a>
    </div>

    <div class="flex justify-center">
        @include('admin.mahasiswa.partials.kartu-content', ['mahasiswa' => $mahasiswa])
    </div>
</x-app-layout>
