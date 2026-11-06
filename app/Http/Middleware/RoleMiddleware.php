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
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login');
        }

        $user = Auth::user();

        $hasAccess = collect(explode('|', $role))
            ->map(fn ($requiredRole) => trim($requiredRole))
            ->contains(fn ($requiredRole) => match ($requiredRole) {
                'admin' => in_array($user->role, ['superadmin', 'admin_fakultas']),
                'superadmin' => $user->role === 'superadmin',
                default => $user->role === $requiredRole,
            });

        if (! $hasAccess) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
