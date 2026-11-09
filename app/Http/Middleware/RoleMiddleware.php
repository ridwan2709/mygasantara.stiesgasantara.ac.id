<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login');
        }

        $user = Auth::user();
        $allowedRoles = [];

        foreach ($roles as $role) {
            foreach (explode('|', $role) as $single) {
                $allowedRoles = array_merge($allowedRoles, $this->expandRole(trim($single)));
            }
        }

        $hasAccess = in_array($user->role, $allowedRoles, true)
            || $user->hasAnyRole($allowedRoles);

        if (! $hasAccess) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }

    private function expandRole(string $role): array
    {
        return match ($role) {
            'admin' => ['superadmin', 'admin_fakultas', 'admin_prodi'],
            'admin_fakultas' => ['admin_fakultas', 'admin_prodi'],
            default => [$role],
        };
    }
}