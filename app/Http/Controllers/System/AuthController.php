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
            $maintenanceMode = \App\Models\Setting::where('key', 'maintenance_mode')->value('value');
            $isMaintenanceActive = ($maintenanceMode === '1' || $maintenanceMode === 'true');

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
            $maintenanceMode = \App\Models\Setting::where('key', 'maintenance_mode')->value('value');
            $isMaintenanceActive = ($maintenanceMode === '1' || $maintenanceMode === 'true');

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

            // Record login log
            $rawIp = $request->header('CF-Connecting-IP')
                ?? $request->header('X-Forwarded-For')
                ?? $request->ip();

            if (str_contains($rawIp, ',')) {
                $rawIp = trim(explode(',', $rawIp)[0]);
            }
            $ip = $rawIp;

            $isPublicIp = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

            $lat = $request->input('latitude');
            $lon = $request->input('longitude');
            $source = ($lat && $lon) ? 'gps' : null;

            $city = null;
            $region = null;
            $country = null;
            $isp = null;

            if ($isPublicIp) {
                try {
                    $response = Http::timeout(2)->get("http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,regionName,city,district,zip,lat,lon,isp,org");
                    if ($response->successful() && $response->json('status') === 'success') {
                        $city = $response->json('city');
                        $region = $response->json('regionName');
                        $country = $response->json('country');
                        $isp = $response->json('isp') ?: $response->json('org');

                        // Jika GPS tidak tersedia dari browser, gunakan estimasi koordinat dari IP
                        if (!$lat || !$lon) {
                            $lat = $response->json('lat');
                            $lon = $response->json('lon');
                            $source = 'ip';
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore API error
                }
            } else {
                $city = ($ip === '127.0.0.1' || $ip === '::1') ? 'Lokal (Localhost)' : 'Jaringan Lokal (LAN)';
                if (!$source) {
                    $source = 'local';
                }
            }

            // Susun data detail lokasi (format JSON ringkas agar muat di kolom location varchar 255)
            $locationData = array_filter([
                'city' => \Illuminate\Support\Str::limit($city, 50, ''),
                'region' => \Illuminate\Support\Str::limit($region, 50, ''),
                'country' => \Illuminate\Support\Str::limit($country, 40, ''),
                'isp' => \Illuminate\Support\Str::limit($isp, 50, ''),
                'lat' => $lat ? round((float)$lat, 6) : null,
                'lon' => $lon ? round((float)$lon, 6) : null,
                'source' => $source, // 'gps', 'ip', 'local'
            ], fn($val) => !is_null($val) && $val !== '');

            $location = !empty($locationData) ? json_encode($locationData, JSON_UNESCAPED_UNICODE) : null;
            if ($location && strlen($location) > 255) {
                $fallback = array_filter([$city, $region, $country]);
                $location = !empty($fallback) ? \Illuminate\Support\Str::limit(implode(', ', $fallback), 250) : null;
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
