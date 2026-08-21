<?php

namespace App\Http\Controllers;

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
    public function showLogin()
    {
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
            $request->session()->regenerate();

            // Record login log
            $ip = $request->ip();
            $location = null;
            if ($ip && $ip !== '127.0.0.1' && $ip !== '::1') {
                try {
                    $response = Http::timeout(2)->get("http://ip-api.com/json/{$ip}");
                    if ($response->successful() && $response->json('status') === 'success') {
                        $location = $response->json('city') . ', ' . $response->json('country');
                    }
                } catch (\Exception $e) {
                    // Ignore, let location be null
                }
            } else {
                $location = 'Lokal (Localhost)';
            }

            LoginLog::create([
                'user_id' => Auth::id(),
                'ip_address' => $ip,
                'user_agent' => $request->userAgent(),
                'location' => $location
            ]);

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
