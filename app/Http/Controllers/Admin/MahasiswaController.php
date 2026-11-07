<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\User;
use App\Services\AkademikCalculationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class MahasiswaController extends Controller
{
    protected AkademikCalculationService $calculationService;

    public function __construct(AkademikCalculationService $calculationService)
    {
        $this->calculationService = $calculationService;
    }

    public function index(Request $request)
    {
        $query = $this->accessibleMahasiswaQuery($request)
            ->with(['user', 'prodi.fakultas', 'dosenPa.user']);
        $isFacultyAdmin = $request->user()->role === 'admin_fakultas';
        $isProgramAdmin = $request->user()->role === 'admin_prodi';

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nim', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($q2) => $q2->where('name', 'like', "%{$search}%"));
            });
        }

        // Filter by fakultas
        if ($fakultasId = $request->get('fakultas_id')) {
            $query->whereHas('prodi', function ($q) use ($fakultasId) {
                $q->where('fakultas_id', $fakultasId);
            });
        }

        // Filter by prodi
        if ($prodiId = $request->get('prodi_id')) {
            $query->where('prodi_id', $prodiId);
        }

        // Filter by angkatan
        if ($angkatan = $request->get('angkatan')) {
            $query->where('angkatan', $angkatan);
        }

        // Variable Sorting
        $sortColumn = $request->get('sort', 'nim');
        $sortDirection = $request->get('order', 'asc');

        if ($sortColumn === 'name') {
            $query->join('users', 'mahasiswa.user_id', '=', 'users.id')
                ->select('mahasiswa.*')
                ->orderBy('users.name', $sortDirection);
        } elseif ($sortColumn === 'prodi') {
            $query->join('prodi', 'mahasiswa.prodi_id', '=', 'prodi.id')
                ->select('mahasiswa.*')
                ->orderBy('prodi.nama', $sortDirection);
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }

        $mahasiswa = $query->paginate(config('siakad.pagination', 15))->withQueryString();

        $fakultasList = match ($request->user()->role) {
            'admin_fakultas' => Fakultas::whereKey($request->user()->fakultas_id)->get(),
            'admin_prodi' => Fakultas::whereHas(
                'prodi',
                fn ($query) => $query->whereKey($request->user()->prodi_id)
            )->get(),
            default => Fakultas::orderBy('nama')->get(),
        };
        $prodiList = $this->accessibleProdiQuery($request)->with('fakultas')->orderBy('nama')->get();
        $angkatanQuery = $this->accessibleMahasiswaQuery($request);
        $angkatanList = $angkatanQuery
            ->distinct()
            ->pluck('angkatan')
            ->sort()
            ->reverse();
        $dosenList = $this->accessibleDosenQuery($request)->with('user')->get();

        return view('admin.mahasiswa.index', compact('mahasiswa', 'fakultasList', 'prodiList', 'angkatanList', 'dosenList', 'isFacultyAdmin', 'isProgramAdmin'));
    }

    public function store(Request $request)
    {
        $this->authorizeFacultyAssignment($request, $request->input('prodi_id'), $request->input('dosen_pa_id'));

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'nim' => 'required|string|unique:mahasiswa,nim',
            'prodi_id' => ['required', $this->prodiScopeRule($request)],
            'angkatan' => 'required|numeric|min:2000|max:'.(date('Y') + 1),
            'dosen_pa_id' => 'nullable|exists:dosen,id',
        ]);
        $this->applyProdiScope($request, $validated);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'mahasiswa',
            ]);

            Mahasiswa::create([
                'user_id' => $user->id,
                'nim' => $validated['nim'],
                'prodi_id' => $validated['prodi_id'],
                'angkatan' => $validated['angkatan'],
                'dosen_pa_id' => $validated['dosen_pa_id'] ?? null,
                'status' => 'aktif',
            ]);
        });

        return back()->with('success', 'Mahasiswa berhasil ditambahkan.');
    }

    public function update(Request $request, Mahasiswa $mahasiswa)
    {
        $mahasiswa = $this->accessibleMahasiswaQuery($request)->findOrFail($mahasiswa->id);
        $this->authorizeFacultyAssignment($request, $request->input('prodi_id'), $request->input('dosen_pa_id'));

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$mahasiswa->user_id,
            'nim' => 'required|string|unique:mahasiswa,nim,'.$mahasiswa->id,
            'prodi_id' => ['required', $this->prodiScopeRule($request)],
            'angkatan' => 'required|numeric',
            'dosen_pa_id' => 'nullable|exists:dosen,id',
            'status' => 'required|in:aktif,cuti,lulus,do',
            'password' => 'nullable|string|min:8',
        ]);
        $this->applyProdiScope($request, $validated);

        DB::transaction(function () use ($validated, $mahasiswa) {
            $mahasiswa->user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            if (! empty($validated['password'])) {
                $mahasiswa->user->update(['password' => Hash::make($validated['password'])]);
            }

            $mahasiswa->update([
                'nim' => $validated['nim'],
                'prodi_id' => $validated['prodi_id'],
                'angkatan' => $validated['angkatan'],
                'dosen_pa_id' => $validated['dosen_pa_id'] ?? null,
                'status' => $validated['status'],
            ]);
        });

        return back()->with('success', 'Mahasiswa berhasil diupdate.');
    }

    public function destroy(Request $request, Mahasiswa $mahasiswa)
    {
        $this->ensureMahasiswaScope($mahasiswa);
        $mahasiswa = $this->accessibleMahasiswaQuery($request)->findOrFail($mahasiswa->id);

        // Check if mahasiswa has KRS
        if ($mahasiswa->krs()->exists()) {
            return back()->withErrors(['error' => 'Tidak dapat menghapus mahasiswa yang memiliki data KRS.']);
        }

        DB::transaction(function () use ($mahasiswa) {
            $userId = $mahasiswa->user_id;
            $mahasiswa->delete();
            User::destroy($userId);
        });

        return back()->with('success', 'Mahasiswa berhasil dihapus.');
    }

    public function export(Request $request)
    {
        $query = $this->accessibleMahasiswaQuery($request)->with(['user', 'prodi']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nim', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($q2) => $q2->where('name', 'like', "%{$search}%"));
            });
        }
        if ($request->user()->role !== 'admin_fakultas' && ($fakultasId = $request->get('fakultas_id'))) {
            $query->whereHas('prodi', function ($q) use ($fakultasId) {
                $q->where('fakultas_id', $fakultasId);
            });
        }
        if ($prodiId = $request->get('prodi_id')) {
            $query->where('prodi_id', $prodiId);
        }
        if ($angkatan = $request->get('angkatan')) {
            $query->where('angkatan', $angkatan);
        }
        $query->orderBy('nim');

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['NIM', 'Nama Mahasiswa', 'Prodi', 'Angkatan', 'Status', 'IPK']);

            $query->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->nim,
                        $row->user->name ?? '-',
                        $row->prodi->nama ?? '-',
                        $row->angkatan,
                        $row->status,
                        $row->ipk ?? 0,
                    ]);
                }
            });
            fclose($handle);
        }, 'data-mahasiswa-'.date('Y-m-d').'.csv');
    }

    public function show(Mahasiswa $mahasiswa)
    {
        $this->ensureMahasiswaScope($mahasiswa);
        $mahasiswa = $this->accessibleMahasiswaQuery(request())->findOrFail($mahasiswa->id);
        $mahasiswa->load(['user', 'prodi.fakultas', 'krs.tahunAkademik', 'krs.krsDetail.kelas.mataKuliah']);

        $ipkData = $this->calculationService->calculateIPK($mahasiswa);
        $ipsHistory = $this->calculationService->getIPSHistory($mahasiswa);
        $gradeDistribution = $this->calculationService->getGradeDistribution($mahasiswa);

        return view('admin.mahasiswa.show', compact('mahasiswa', 'ipkData', 'ipsHistory', 'gradeDistribution'));
    }

    private function accessibleMahasiswaQuery(Request $request): Builder
    {
        $query = Mahasiswa::query();
        $user = $request->user();

        if ($user->role === 'admin_fakultas') {
            abort_unless($user->fakultas_id, 403, 'Admin fakultas belum memiliki fakultas');

            $query->whereHas(
                'prodi',
                fn ($prodiQuery) => $prodiQuery->where('fakultas_id', $user->fakultas_id)
            );
        } elseif ($user->role === 'admin_prodi') {
            abort_unless($user->prodi_id, 403, 'Admin prodi belum memiliki program studi');
            $query->where('prodi_id', $user->prodi_id);
        }

        return $query;
    }

    private function accessibleProdiQuery(Request $request): Builder
    {
        $query = Prodi::query();
        $user = $request->user();

        if ($user->role === 'admin_fakultas') {
            abort_unless($user->fakultas_id, 403, 'Admin fakultas belum memiliki fakultas');
            $query->where('fakultas_id', $user->fakultas_id);
        } elseif ($user->role === 'admin_prodi') {
            abort_unless($user->prodi_id, 403, 'Admin prodi belum memiliki program studi');
            $query->whereKey($user->prodi_id);
        }

        return $query;
    }

    private function accessibleDosenQuery(Request $request): Builder
    {
        $query = Dosen::query();
        $user = $request->user();

        if ($user->role === 'admin_fakultas') {
            abort_unless($user->fakultas_id, 403, 'Admin fakultas belum memiliki fakultas');
            $query->whereHas('prodi', fn ($prodiQuery) => $prodiQuery->where('fakultas_id', $user->fakultas_id));
        } elseif ($user->role === 'admin_prodi') {
            abort_unless($user->prodi_id, 403, 'Admin prodi belum memiliki program studi');
            $query->where('prodi_id', $user->prodi_id);
        }

        return $query;
    }

    private function prodiScopeRule(Request $request): Exists
    {
        $rule = Rule::exists('prodi', 'id');
        $user = $request->user();

        if ($user->role === 'admin_fakultas') {
            abort_unless($user->fakultas_id, 403, 'Admin fakultas belum memiliki fakultas');

            return $rule->where('fakultas_id', $user->fakultas_id);
        }

        if ($user->role === 'admin_prodi') {
            abort_unless($user->prodi_id, 403, 'Admin prodi belum memiliki program studi');

            return $rule->where('id', $user->prodi_id);
        }

        return $rule;
    }

    private function authorizeFacultyAssignment(Request $request, mixed $prodiId, mixed $dosenId): void
    {
        if (! in_array($request->user()->role, ['admin_fakultas', 'admin_prodi'], true)) {
            return;
        }

        if ($prodiId) {
            abort_unless($this->accessibleProdiQuery($request)->whereKey($prodiId)->exists(), 403, 'Prodi tidak termasuk cakupan akun Anda.');
        }

        if ($dosenId) {
            abort_unless($this->accessibleDosenQuery($request)->whereKey($dosenId)->exists(), 403, 'Dosen PA tidak termasuk cakupan akun Anda.');
        }
    }

    private function applyProdiScope(Request $request, array &$validated): void
    {
        if ($request->user()->role !== 'admin_prodi') {
            return;
        }

        abort_unless($request->user()->prodi_id, 403, 'Admin prodi belum memiliki program studi');
        $prodiId = (int) $request->user()->prodi_id;
        $validated['prodi_id'] = $prodiId;

        if (! empty($validated['dosen_pa_id'])) {
            abort_unless(
                Dosen::whereKey($validated['dosen_pa_id'])->where('prodi_id', $prodiId)->exists(),
                404
            );
        }
    }

    private function ensureMahasiswaScope(Mahasiswa $mahasiswa): void
    {
        $user = request()->user();

        if ($user->role === 'admin_prodi') {
            abort_unless($user->prodi_id && (int) $mahasiswa->prodi_id === (int) $user->prodi_id, 404);
        }
    }
}
