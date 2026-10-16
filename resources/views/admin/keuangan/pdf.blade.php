<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Honorarium</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        p { margin-top: 0; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #9ca3af; padding: 7px; }
        th { background: #e5e7eb; text-align: left; }
        .number { text-align: right; }
    </style>
</head>
<body>
    <h1>Rekap Honorarium Dosen</h1>
    <p>Periode {{ \Carbon\Carbon::create($tahun, $bulan)->locale('id')->monthName }} {{ $tahun }}</p>
    <table>
        <thead><tr><th>NIDN</th><th>Nama Dosen</th><th>Jumlah Hadir</th><th class="number">Honor Mengajar</th><th class="number">Insentif</th><th class="number">Total Penerimaan</th><th>Status</th></tr></thead>
        <tbody>
            @foreach($honorariumList as $honorarium)
                <tr>
                    <td>{{ $honorarium->dosen->nidn }}</td>
                    <td>{{ $honorarium->dosen->user->name }}</td>
                    <td>{{ $honorarium->total_kehadiran }}</td>
                    <td class="number">Rp{{ number_format((float) $honorarium->total_honor_mengajar, 0, ',', '.') }}</td>
                    <td class="number">Rp{{ number_format((float) $honorarium->total_insentif_manual, 0, ',', '.') }}</td>
                    <td class="number">Rp{{ number_format((float) $honorarium->total_penerimaan, 0, ',', '.') }}</td>
                    <td>{{ $honorarium->status_pembayaran === 'paid' ? 'Dibayar' : 'Draft' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
