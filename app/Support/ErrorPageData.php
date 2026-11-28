<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class ErrorPageData
{
    /**
     * Peta tunggal kode status -> judul, pesan ramah, nada warna, dan ikon.
     */
    public static function forStatus(int $status, ?int $retryAfter = null): array
    {
        return match ($status) {
            400 => [
                'status' => 400,
                'title' => 'Permintaan Tidak Valid',
                'message' => 'Permintaan tidak dapat diproses. Silakan periksa kembali data atau tautan yang Anda buka.',
                'tone' => 'warn',
                'icon' => 'bad_request',
            ],
            401 => [
                'status' => 401,
                'title' => 'Sesi Berakhir',
                'message' => 'Anda perlu masuk kembali untuk melanjutkan akses ke halaman ini.',
                'tone' => 'warn',
                'icon' => 'forbidden',
            ],
            403 => [
                'status' => 403,
                'title' => 'Akses Dibatasi',
                'message' => 'Anda tidak memiliki hak akses untuk membuka halaman atau tindakan ini.',
                'tone' => 'warn',
                'icon' => 'forbidden',
            ],
            404 => [
                'status' => 404,
                'title' => 'Halaman Tidak Ditemukan',
                'message' => 'Tautan mungkin keliru atau halaman sudah dipindahkan.',
                'tone' => 'info',
                'icon' => 'not_found',
            ],
            405 => [
                'status' => 405,
                'title' => 'Metode Tidak Diizinkan',
                'message' => 'Permintaan ini tidak didukung pada alamat yang dituju.',
                'tone' => 'warn',
                'icon' => 'bad_request',
            ],
            408 => [
                'status' => 408,
                'title' => 'Waktu Permintaan Habis',
                'message' => 'Koneksi atau unggahan membutuhkan waktu terlalu lama. Periksa jaringan Anda lalu coba lagi.',
                'tone' => 'warn',
                'icon' => 'timeout',
            ],
            413 => [
                'status' => 413,
                'title' => 'Ukuran File Terlalu Besar',
                'message' => 'File yang diunggah melebihi batas ukuran server. Perkecil ukuran file lalu coba unggah kembali.',
                'tone' => 'danger',
                'icon' => 'timeout',
            ],
            419 => [
                'status' => 419,
                'title' => 'Sesi Halaman Kedaluwarsa',
                'message' => 'Token keamanan halaman sudah kedaluwarsa. Muat ulang halaman lalu kirim ulang formulir Anda.',
                'tone' => 'warn',
                'icon' => 'expired',
            ],
            429 => [
                'status' => 429,
                'title' => 'Terlalu Banyak Permintaan',
                'message' => $retryAfter
                    ? "Anda mengirim terlalu banyak permintaan. Silakan tunggu {$retryAfter} detik sebelum mencoba lagi."
                    : 'Anda mengirim terlalu banyak permintaan dalam waktu singkat. Silakan tunggu sebentar lalu coba lagi.',
                'tone' => 'warn',
                'icon' => 'rate_limit',
            ],
            503 => [
                'status' => 503,
                'title' => 'Sedang Pemeliharaan Sistem',
                'message' => 'Layanan sedang dalam pemeliharaan atau sibuk sementara. Kami segera kembali.',
                'tone' => 'neutral',
                'icon' => 'maintenance',
            ],
            default => [
                'status' => $status >= 500 ? 500 : $status,
                'title' => 'Terjadi Kendala di Sisi Kami',
                'message' => 'Terjadi kesalahan internal pada server. Silakan coba beberapa saat lagi.',
                'tone' => 'danger',
                'icon' => 'server',
            ],
        };
    }

    /**
     * Bangun payload props aman (tahan kegagalan DB/session).
     */
    public static function buildPayload(
        int $status,
        Request $request,
        ?string $reference = null,
        ?int $retryAfter = null,
        bool $safeMode = false
    ): array {
        $meta = self::forStatus($status, $retryAfter);

        $isPublicContext = $request->is('verifikasi-surat*') || $request->is('verifikasi-notulen*');
        $isKioskContext = $request->is('kiosk-rfid*');

        $homeUrl = '/';
        $homeLabel = 'Ke Beranda';
        $effectiveRole = null;
        $canSwitchAccount = false;

        if ($isKioskContext) {
            $homeUrl = Route::has('attendance.rfid.kiosk') ? route('attendance.rfid.kiosk') : '/kiosk-rfid';
            $homeLabel = 'Kembali ke Kiosk';
        } elseif ($isPublicContext) {
            $homeUrl = '/';
            $homeLabel = 'Ke Beranda';
        } elseif (!$safeMode) {
            try {
                $user = $request->user();
                if ($user) {
                    $homeUrl = Route::has('dashboard') ? route('dashboard') : '/dashboard';
                    $homeLabel = 'Ke Dashboard';
                    if ($status === 403 && method_exists($user, 'getRoleNames')) {
                        $effectiveRole = $user->getRoleNames()->first();
                    }
                    $accounts = $request->hasSession() ? $request->session()->get('multi_accounts', []) : [];
                    $canSwitchAccount = is_array($accounts) && count($accounts) > 1;
                } else {
                    $homeUrl = Route::has('login') ? route('login') : '/login';
                    $homeLabel = 'Ke Halaman Login';
                }
            } catch (\Throwable) {
                $homeUrl = '/';
                $homeLabel = 'Ke Beranda';
            }
        }

        return [
            ...$meta,
            'reference' => $reference,
            'retryAfter' => $retryAfter,
            'homeUrl' => $homeUrl,
            'homeLabel' => $homeLabel,
            'effectiveRole' => $effectiveRole,
            'isPublicContext' => $isPublicContext,
            'isKioskContext' => $isKioskContext,
            'canSwitchAccount' => $canSwitchAccount,
        ];
    }
}
