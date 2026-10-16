<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class HonorariumExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $honorariumList) {}

    public function collection(): Collection
    {
        return $this->honorariumList;
    }

    public function headings(): array
    {
        return [
            'NIDN',
            'Nama Dosen',
            'Periode',
            'Total Kehadiran Terverifikasi',
            'Honor Mengajar',
            'Insentif Manual',
            'Total Penerimaan',
            'Status Pembayaran',
            'Tanggal Bayar',
        ];
    }

    public function map($honorarium): array
    {
        return [
            $honorarium->dosen->nidn,
            $honorarium->dosen->user->name,
            sprintf('%02d/%d', $honorarium->bulan, $honorarium->tahun),
            $honorarium->total_kehadiran,
            $honorarium->total_honor_mengajar,
            $honorarium->total_insentif_manual,
            $honorarium->total_penerimaan,
            $honorarium->status_pembayaran,
            $honorarium->tanggal_bayar?->format('Y-m-d'),
        ];
    }
}
