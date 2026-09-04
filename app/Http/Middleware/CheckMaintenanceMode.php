<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $maintenanceMode = Setting::where('key', 'maintenance_mode')->value('value');
        $isMaintenanceActive = ($maintenanceMode === '1' || $maintenanceMode === 'true');

        // Jika user adalah Super Admin asli di database, selalu izinkan akses penuh
        $user = $request->user();
        if ($user && method_exists($user, 'isActualSuperadmin') && $user->isActualSuperadmin()) {
            return $next($request);
        }

        // Daftar nama route atau path yang dikecualikan dari blokir maintenance
        $exemptRoutes = [
            'maintenance',
            'login',
            'login.attempt',
            'logout',
            'admin.maintenance.switch-role',
        ];

        // Izinkan aset statis dan rute publik penting
        if (
            $request->is('storage/*') ||
            $request->is('build/*') ||
            $request->is('img/*') ||
            $request->is('logo.png') ||
            $request->is('favicon.ico') ||
            $request->is('manifest.json') ||
            $request->is('sw.js') ||
            $request->is('up')
        ) {
            return $next($request);
        }

        if ($isMaintenanceActive) {
            // Cek apakah route saat ini dikecualikan
            foreach ($exemptRoutes as $route) {
                if ($request->routeIs($route)) {
                    return $next($request);
                }
            }

            // Jika user biasa sedang login saat maintenance diaktifkan, logout seketika
            if ($user) {
                \Illuminate\Support\Facades\Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            // Redirect pengguna biasa / tamu ke halaman maintenance
            return redirect()->route('maintenance');
        }

        // Jika maintenance tidak aktif, cegah tamu/user biasa membuka /maintenance
        if ($request->routeIs('maintenance')) {
            return redirect()->route('welcome');
        }

        return $next($request);
    }
}
