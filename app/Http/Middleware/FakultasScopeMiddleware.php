<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FakultasScopeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * This middleware adds the assigned program and faculty scopes to admin requests.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Superadmin bypasses all restrictions
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if ($user->role === 'admin_prodi') {
            $user->loadMissing('prodi');

            abort_unless($user->prodi, 403, 'Akun admin prodi belum memiliki prodi yang ditetapkan.');

            $request->merge([
                'prodi_scope' => $user->prodi_id,
                'prodi_scoped' => true,
                'fakultas_scope' => $user->prodi->fakultas_id,
                'fakultas_scoped' => true,
            ]);
        }

        return $next($request);
    }
}
