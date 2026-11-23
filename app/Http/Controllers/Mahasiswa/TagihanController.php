<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\TagihanMahasiswa;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TagihanController extends Controller
{
    public function index(): View
    {
        $mahasiswa = Auth::user()->mahasiswa;

        abort_unless($mahasiswa, 403);

        $tagihanList = TagihanMahasiswa::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->latest()
            ->paginate(20);

        return view('mahasiswa.tagihan.index', compact('tagihanList'));
    }
}
