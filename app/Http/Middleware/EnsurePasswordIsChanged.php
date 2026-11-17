<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            // Allow password update routes, profile view, logout, and inertia assets
            $allowedRoutes = [
                'profile.edit',
                'profile.password',
                'logout',
            ];

            $currentRouteName = $request->route() ? $request->route()->getName() : null;

            if (!in_array($currentRouteName, $allowedRoutes) && !$request->is('logout')) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Anda wajib mengganti password default sebelum dapat melanjutkan.',
                        'must_change_password' => true,
                    ], 403);
                }

                return redirect()->route('profile.edit')->with(
                    'warning',
                    'Demi keamanan akun, Anda wajib mengganti password default Anda terlebih dahulu sebelum menggunakan sistem.'
                );
            }
        }

        return $next($request);
    }
}
