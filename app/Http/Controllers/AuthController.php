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
    public function showLogin(Request $request)
    {
        if (Auth::check() && !$request->session()->get('is_adding_account')) {
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
            $request->session()->regenerate();

            $user = Auth::user();
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
