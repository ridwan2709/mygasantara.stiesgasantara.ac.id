<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu Mahasiswa {{ $mahasiswa->nim }}</title>
    <style>
        @page { size: 90mm 140mm; margin: 5mm; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #1f2937; }
        .card { width: 80mm; height: 54mm; border: 1px solid #cbd5e1; border-radius: 4mm; overflow: hidden; }
        .header { height: 13mm; background: #a53041; color: #fff; text-align: center; padding-top: 2mm; }
        .campus { font-size: 8pt; font-weight: bold; }
        .subtitle { font-size: 5pt; margin-top: 1mm; letter-spacing: 1px; }
        .body { height: 39mm; padding: 3mm 4mm; }
        .name { height: 7mm; font-size: 9pt; font-weight: bold; text-align: center; color: #162033; }
        .nim { height: 5mm; font-size: 8pt; font-weight: bold; text-align: center; color: #a53041; }
        .info { font-size: 6pt; color: #475569; border-top: 1px solid #e5e7eb; padding-top: 2mm; }
        .row { height: 4mm; }
        .label { display: inline-block; width: 19mm; color: #64748b; }
        .value { font-weight: bold; color: #1f2937; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div class="campus">STIES Gasantara</div>
            <div class="subtitle">KARTU TANDA MAHASISWA</div>
        </div>
        <div class="body">
            <div class="name">{{ $mahasiswa->user->name ?? '-' }}</div>
            <div class="nim">{{ $mahasiswa->nim }}</div>
            <div class="info">
                <div class="row"><span class="label">Prodi</span><span class="value">{{ $mahasiswa->prodi->nama ?? '-' }}</span></div>
                <div class="row"><span class="label">Angkatan</span><span class="value">{{ $mahasiswa->angkatan }}</span></div>
                @if($mahasiswa->rombel)<div class="row"><span class="label">Rombel</span><span class="value">{{ $mahasiswa->rombel }}</span></div>@endif
                <div class="row"><span class="label">Status</span><span class="value">{{ ucfirst($mahasiswa->status) }}</span></div>
            </div>
        </div>
    </div>
</body>
</html>
