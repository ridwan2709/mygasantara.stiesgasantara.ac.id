<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Honorarium;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HonorariumController extends Controller
{
    public function index(Request $request): Response
    {
        $dosen = $request->user()->dosen;
        abort_unless($dosen, 404);

        $honorariumList = Honorarium::query()
            ->where('dosen_id', $dosen->id)
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->paginate(12);

        return response()->view('dosen.honorarium.index', compact('dosen', 'honorariumList'));
    }

    public function slip(Request $request, Honorarium $honorarium): Response
    {
        $dosen = $request->user()->dosen;
        abort_unless($dosen && $honorarium->dosen_id === $dosen->id, 404);

        return Pdf::loadView('dosen.honorarium.slip', compact('honorarium'))
            ->stream("slip-honor-{$honorarium->tahun}-".str_pad((string) $honorarium->bulan, 2, '0', STR_PAD_LEFT).'.pdf');
    }
}
