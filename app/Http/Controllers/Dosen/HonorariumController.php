<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Honorarium;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HonorariumController extends Controller
{
    public function index(Request $request): Response
    {
        $dosen = $request->user()->dosen;
        $isAdminFakultas = $request->user()->role === 'admin_fakultas';
        abort_unless($isAdminFakultas || $dosen, 404);

        $honorariumList = $this->accessibleHonorariumQuery($request)
            ->with('dosen.user')
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->paginate(12);

        return response()->view('dosen.honorarium.index', compact('dosen', 'honorariumList', 'isAdminFakultas'));
    }

    public function slip(Request $request, Honorarium $honorarium): Response
    {
        abort_unless(
            $this->accessibleHonorariumQuery($request)->whereKey($honorarium->id)->exists(),
            404
        );

        return Pdf::loadView('dosen.honorarium.slip', compact('honorarium'))
            ->stream("slip-honor-{$honorarium->tahun}-".str_pad((string) $honorarium->bulan, 2, '0', STR_PAD_LEFT).'.pdf');
    }

    private function accessibleHonorariumQuery(Request $request): Builder
    {
        $user = $request->user();

        if ($user->role === 'admin_fakultas') {
            abort_unless($user->fakultas_id, 403, 'Admin fakultas belum memiliki fakultas');

            return Honorarium::query()->whereHas(
                'dosen.prodi',
                fn ($query) => $query->where('fakultas_id', $user->fakultas_id)
            );
        }

        $dosen = $user->dosen;
        abort_unless($dosen, 404);

        return Honorarium::query()->where('dosen_id', $dosen->id);
    }
}
