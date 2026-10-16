<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MasterDataImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MasterDataImportController extends Controller
{
    private const PAGES = [
        'fakultas' => ['route' => 'admin.fakultas.index', 'label' => 'Fakultas'],
        'prodi' => ['route' => 'admin.prodi.index', 'label' => 'Program Studi'],
        'mahasiswa' => ['route' => 'admin.mahasiswa.index', 'label' => 'Mahasiswa'],
        'dosen' => ['route' => 'admin.dosen.index', 'label' => 'Dosen'],
        'mata-kuliah' => ['route' => 'admin.mata-kuliah.index', 'label' => 'Mata Kuliah'],
        'kelas' => ['route' => 'admin.kelas.index', 'label' => 'Kelas'],
        'tahun-akademik' => ['route' => 'admin.tahun-akademik.index', 'label' => 'Tahun Akademik'],
        'ruangan' => ['route' => 'admin.ruangan.index', 'label' => 'Ruangan'],
    ];

    public function import(Request $request, string $type, MasterDataImportService $service): RedirectResponse
    {
        $this->authorizeType($type, $request->user());

        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ], [
            'file.required' => 'Pilih file CSV yang akan diimpor.',
            'file.file' => 'File yang diunggah tidak dapat dibaca.',
            'file.mimes' => 'Format file harus CSV (.csv).',
            'file.max' => 'Ukuran file CSV maksimal 10 MB.',
        ]);

        if ($validator->fails()) {
            return redirect()->route(self::PAGES[$type]['route'])
                ->with('import_failure', 'File tidak dapat diimpor. Perbaiki masalah berikut, lalu unggah kembali.')
                ->withErrors($validator, 'masterDataImport');
        }

        try {
            $count = $service->import($type, $validator->validated()['file'], $request->user());
        } catch (ValidationException $exception) {
            return redirect()->route(self::PAGES[$type]['route'])
                ->with('import_failure', 'Impor gagal. Tidak ada data dari file ini yang disimpan. Perbaiki baris dan kolom berikut, lalu unggah kembali.')
                ->withErrors($exception->errors(), 'masterDataImport');
        }

        $label = self::PAGES[$type]['label'];

        return redirect()->route(self::PAGES[$type]['route'])
            ->with('import_success', "{$count} data {$label} berhasil diimpor.");
    }

    public function template(Request $request, string $type, MasterDataImportService $service): StreamedResponse
    {
        $this->authorizeType($type, $request->user());

        $headers = [...$service->headers($type), 'penanda_contoh'];
        $rows = $service->templateRows($type);

        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }
            fclose($handle);
        }, "template-{$type}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function authorizeType(string $type, User $user): void
    {
        if (! isset(self::PAGES[$type])) {
            abort(404);
        }

        if (in_array($type, ['fakultas', 'tahun-akademik'], true) && ! $user->isSuperAdmin()) {
            abort(403, 'Hanya superadmin yang dapat mengimpor data ini.');
        }
    }
}
