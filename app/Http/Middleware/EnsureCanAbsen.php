<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanAbsen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login');
        }

        $user = Auth::user();
        $isAttendanceRole = in_array($user->role, ['superadmin', 'bendahara', 'staff'], true);

        if (! $user->boleh_absen && ! $isAttendanceRole) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Anda tidak diberi akses absen.'], 403);
            }

            abort(403, 'Anda tidak diberi akses absen.');
        }

        return $next($request);
    }
}
