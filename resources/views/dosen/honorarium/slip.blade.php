<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip Honorarium {{ $honorarium->tahun }}-{{ $honorarium->bulan }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        .slip { max-width: 720px; margin: 24px auto; border: 1px solid #9ca3af; padding: 28px; }
        h1 { margin: 0 0 5px; font-size: 20px; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        td { border-bottom: 1px solid #d1d5db; padding: 10px 4px; }
        .amount { text-align: right; }
        .total { font-weight: bold; font-size: 14px; }
        .signature { margin-top: 60px; text-align: right; }
    </style>
</head>
<body>
    <div class="slip">
        <h1>Slip Honorarium Dosen</h1>
        <p class="muted">Periode {{ \Carbon\Carbon::create($honorarium->tahun, $honorarium->bulan)->locale('id')->monthName }} {{ $honorarium->tahun }}</p>
        <p><strong>{{ $honorarium->dosen->user->name }}</strong><br>NIDN {{ $honorarium->dosen->nidn }}</p>
        <table>
            <tr><td>Jumlah kehadiran mengajar terverifikasi</td><td class="amount">{{ $honorarium->total_kehadiran }} pertemuan</td></tr>
            <tr><td>Honor mengajar</td><td class="amount">Rp{{ number_format((float) $honorarium->total_honor_mengajar, 0, ',', '.') }}</td></tr>
            <tr><td>Insentif manual</td><td class="amount">Rp{{ number_format((float) $honorarium->total_insentif_manual, 0, ',', '.') }}</td></tr>
            <tr class="total"><td>Total penerimaan</td><td class="amount">Rp{{ number_format((float) $honorarium->total_penerimaan, 0, ',', '.') }}</td></tr>
            <tr><td>Status pembayaran</td><td class="amount">{{ $honorarium->status_pembayaran === 'paid' ? 'Dibayar' : 'Draft' }}</td></tr>
            @if($honorarium->tanggal_bayar)<tr><td>Tanggal pembayaran</td><td class="amount">{{ $honorarium->tanggal_bayar->format('d/m/Y') }}</td></tr>@endif
        </table>
        <div class="signature"><p>Bagian Keuangan</p><br><br><p>(________________________)</p></div>
    </div>
</body>
</html>
