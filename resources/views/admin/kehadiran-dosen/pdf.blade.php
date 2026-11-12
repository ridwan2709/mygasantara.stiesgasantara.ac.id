<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Kehadiran {{ \Carbon\Carbon::create($year, $month)->locale('id')->monthName }} {{ $year }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 28px 30px 42px;
        }

        body {
            color: #1f2937;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
        }

        .header {
            border-bottom: 3px solid #1d4ed8;
            margin-bottom: 16px;
            padding-bottom: 10px;
        }

        .eyebrow {
            color: #64748b;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        h1 {
            color: #0f172a;
            font-size: 20px;
            margin: 5px 0;
        }

        .period {
            color: #475569;
            font-size: 10px;
            margin: 0;
        }

        .cards {
            margin: 0 -5px 18px;
            width: 100%;
        }

        .cards td {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            padding: 9px 12px;
            text-align: center;
            width: 20%;
        }

        .card-label {
            color: #64748b;
            font-size: 8px;
            text-transform: uppercase;
        }

        .card-value {
            color: #0f172a;
            font-size: 16px;
            font-weight: bold;
            margin-top: 3px;
        }

        h2 {
            color: #0f172a;
            font-size: 13px;
            margin: 17px 0 7px;
        }

        .section-note {
            color: #64748b;
            font-size: 8px;
            margin: -3px 0 8px;
        }

        table.report {
            border-collapse: collapse;
            width: 100%;
        }

        .report th {
            background: #1e3a8a;
            color: #fff;
            font-size: 8px;
            font-weight: bold;
            padding: 8px 7px;
            text-align: left;
            text-transform: uppercase;
        }

        .report td {
            border-bottom: 1px solid #e2e8f0;
            padding: 7px;
        }

        .report tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .center {
            text-align: center !important;
        }

        .total-row td {
            background: #eaf0f9;
            border-top: 2px solid #cbd5e1;
            font-weight: bold;
        }

        .empty {
            color: #64748b;
            padding: 14px !important;
            text-align: center;
        }

        .footer {
            bottom: -24px;
            color: #64748b;
            font-size: 8px;
            left: 0;
            position: fixed;
            right: 0;
        }

        .footer .page-number:after {
            content: counter(page);
        }
    </style>
</head>
<body>
    @php
        $statusLabels = [
            'hadir' => 'Hadir',
            'izin' => 'Izin',
            'sakit' => 'Sakit',
            'tugas' => 'Tugas',
            'alpa' => 'Alpa',
        ];
        $monthLabel = \Carbon\Carbon::create($year, $month)->locale('id')->monthName;
        $totals = collect($statusLabels)->mapWithKeys(fn ($label, $status) => [
            $status => $dosenList->sum(fn ($dosen) => (int) ($rekapDosen[$dosen->id] ?? collect())->where('status', $status)->sum('count')),
        ]);
        $pegawaiTotals = collect($statusLabels)->mapWithKeys(fn ($label, $status) => [
            $status => $rekapPegawai->sum($status),
        ]);
    @endphp

    <div class="header">
        <div class="eyebrow">Laporan Akademik</div>
        <h1>Rekap Kehadiran Dosen dan Pegawai</h1>
        <p class="period">Periode {{ $monthLabel }} {{ $year }}</p>
    </div>

    <table class="cards">
        <tr>
            @foreach($statusLabels as $status => $label)
                <td>
                    <div class="card-label">{{ $label }}</div>
                    <div class="card-value">{{ $stats[$status] ?? 0 }}</div>
                </td>
            @endforeach
        </tr>
    </table>

    <h2>Rekap Kehadiran Dosen</h2>
    <p class="section-note">Persentase hadir dihitung dari jumlah hadir dibanding seluruh catatan kehadiran dosen pada periode ini.</p>
    <table class="report">
        <thead>
            <tr>
                <th style="width: 30px">No.</th>
                <th>Nama Dosen</th>
                <th style="width: 105px">NIDN</th>
                @foreach($statusLabels as $label)
                    <th class="center" style="width: 62px">{{ $label }}</th>
                @endforeach
                <th class="center" style="width: 75px">Hadir</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dosenList as $index => $dosen)
                @php
                    $rekap = $rekapDosen[$dosen->id] ?? collect();
                    $totalDosen = $rekap->sum('count');
                    $hadirDosen = (int) $rekap->where('status', 'hadir')->sum('count');
                    $persentaseDosen = $totalDosen > 0 ? round(($hadirDosen / $totalDosen) * 100) : 0;
                @endphp
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $dosen->user?->name ?? '-' }}</td>
                    <td>{{ $dosen->nidn }}</td>
                    @foreach(array_keys($statusLabels) as $status)
                        <td class="center">{{ $rekap->where('status', $status)->sum('count') }}</td>
                    @endforeach
                    <td class="center">{{ $persentaseDosen }}%</td>
                </tr>
            @empty
                <tr><td class="empty" colspan="9">Tidak ada data dosen.</td></tr>
            @endforelse
        </tbody>
        @if($dosenList->isNotEmpty())
            <tfoot>
                <tr class="total-row">
                    <td colspan="3">Total</td>
                    @foreach(array_keys($statusLabels) as $status)
                        <td class="center">{{ $totals[$status] }}</td>
                    @endforeach
                    <td class="center">-</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <h2>Rekap Kehadiran Pegawai</h2>
    <p class="section-note">Termasuk pegawai dan dosen yang menjalankan tugas admin program studi.</p>
    <table class="report">
        <thead>
            <tr>
                <th style="width: 30px">No.</th>
                <th>Nama</th>
                <th style="width: 145px">Jabatan</th>
                @foreach($statusLabels as $label)
                    <th class="center" style="width: 62px">{{ $label }}</th>
                @endforeach
                <th class="center" style="width: 75px">Hadir</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rekapPegawai as $index => $pegawai)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $pegawai['name'] }}</td>
                    <td>{{ $pegawai['role'] }}</td>
                    @foreach(array_keys($statusLabels) as $status)
                        <td class="center">{{ $pegawai[$status] }}</td>
                    @endforeach
                    <td class="center">{{ $pegawai['persentase'] }}%</td>
                </tr>
            @empty
                <tr><td class="empty" colspan="9">Tidak ada data kehadiran pegawai pada periode ini.</td></tr>
            @endforelse
        </tbody>
        @if($rekapPegawai->isNotEmpty())
            <tfoot>
                <tr class="total-row">
                    <td colspan="3">Total</td>
                    @foreach(array_keys($statusLabels) as $status)
                        <td class="center">{{ $pegawaiTotals[$status] }}</td>
                    @endforeach
                    <td class="center">-</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="footer">
        <table style="width:100%">
            <tr>
                <td style="border:0; padding:0">Dicetak {{ now()->locale('id')->translatedFormat('d F Y H:i') }}</td>
                <td style="border:0; padding:0; text-align:right">Halaman <span class="page-number"></span></td>
            </tr>
        </table>
    </div>
</body>
</html>
