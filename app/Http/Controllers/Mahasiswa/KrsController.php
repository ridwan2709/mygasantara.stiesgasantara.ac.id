<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\TahunAkademik;
use Illuminate\Support\Facades\Auth;

class KrsController extends Controller
{
    public function index()
    {
        $mahasiswa = Auth::user()->mahasiswa;
        if (!$mahasiswa) abort(403, 'Unauthorized');

        $tahunAkademik = TahunAkademik::query()->where('is_active', true)->first();
        $krs = $tahunAkademik
            ? $mahasiswa->krs()
                ->with(['tahunAkademik', 'krsDetail.kelas.mataKuliah', 'krsDetail.kelas.dosen.user'])
                ->where('tahun_akademik_id', $tahunAkademik->id)
                ->first()
            : null;

        return view('mahasiswa.krs.index', compact('krs', 'tahunAkademik'));
    }
}
