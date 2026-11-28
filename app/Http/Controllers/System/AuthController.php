<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use App\Models\LoginLog;

class AuthController extends Controller
{
    /**
     * Tampilkan halaman login.
     */
    public function showLogin(Request $request)
    {
        if (Auth::check() && !$request->session()->get('is_adding_account')) {
            $user = Auth::user();
            $isMaintenanceActive = \App\Models\Setting::getBoolean('maintenance_mode');

            if ($isMaintenanceActive && !$user->isActualSuperadmin()) {
                return redirect()->route('maintenance');
            }

            return redirect()->route('dashboard');
        }
        
        return Inertia::render('Auth/Login');
    }

    /**
     * Proses otentikasi login.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();

            // Pengecekan status mode pemeliharaan
            $isMaintenanceActive = \App\Models\Setting::getBoolean('maintenance_mode');

            if ($isMaintenanceActive && !$user->isActualSuperadmin()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'Sistem sedang dalam mode pemeliharaan (Maintenance). Saat ini akses dibatasi hanya untuk tim IT (namaHarusUnik)',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();
            $accounts = $request->session()->get('multi_accounts', []);
            $isAdding = $request->session()->get('is_adding_account', false);

            $accountData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar ?? null,
                'can_multi_login' => $user->can_multi_login,
            ];

            if ($isAdding) {
                $exists = false;
                foreach($accounts as $acc) {
                    if ($acc['id'] == $user->id) {
                        $exists = true; break;
                    }
                }
                if (!$exists) {
                    $accounts[] = $accountData;
                }
                $request->session()->forget('is_adding_account');
                $request->session()->put('multi_accounts', $accounts);
            } else {
                $request->session()->put('multi_accounts', [$accountData]);
            }

            // Record login log securely using trusted request IP
            $ip = $request->ip();

            $lat = $request->input('latitude') ? (float) $request->input('latitude') : null;
            $lon = $request->input('longitude') ? (float) $request->input('longitude') : null;
            $source = ($lat && $lon) ? 'gps' : null;

            $initialLocation = null;
            if ($lat && $lon) {
                $initialLocation = json_encode([
                    'lat' => round($lat, 6),
                    'lon' => round($lon, 6),
                    'source' => 'gps',
                ]);
            }

            $loginLog = LoginLog::create([
                'user_id' => Auth::id(),
                'ip_address' => $ip,
                'user_agent' => $request->userAgent(),
                'location' => $initialLocation,
            ]);

            // Dispatch async location resolution in background
            \App\Jobs\ResolveLoginLocationJob::dispatch($loginLog->id, $ip, $lat, $lon, $source);

            // Jika user adalah Petugas Koperasi murni, arahkan langsung ke Sistem POS
            if ($user->roles->count() === 1 && $user->hasRole('koperasi')) {
                return redirect()->intended(route('koperasi.pos'));
            }

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'Kredensial yang diberikan tidak cocok dengan data kami.',
        ])->onlyInput('email');
    }

    /**
     * Proses logout.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
