<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;

use App\Models\User;
use App\Notifications\GenericWebPushNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use NotificationChannels\WebPush\PushSubscription;

class PushSubscriptionController extends Controller
{
    /**
     * Daftarkan atau perbarui subscription perangkat user.
     */
    public function store(Request $request)
    {
        $request->validate([
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $contentEncoding = $request->input('contentEncoding', 'aesgcm');

        $user->updatePushSubscription(
            $request->endpoint,
            $request->input('keys.p256dh'),
            $request->input('keys.auth'),
            $contentEncoding
        );

        return response()->json([
            'success' => true,
            'message' => 'Perangkat berhasil didaftarkan untuk Push Notification.',
        ]);
    }

    /**
     * Hapus subscription ketika user mematikan notifikasi.
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'endpoint' => 'required|string',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user) {
            $user->deletePushSubscription($request->endpoint);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription berhasil dihapus.',
        ]);
    }

    /**
     * Ambil VAPID Public Key untuk handshake browser.
     */
    public function vapidPublicKey()
    {
        return response()->json([
            'publicKey' => config('webpush.vapid.public_key'),
        ]);
    }

    /**
     * Status langganan push user yang sedang aktif.
     */
    public function status()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $userSubscriptionsCount = $user ? $user->pushSubscriptions()->count() : 0;
        $totalSystemSubscriptions = PushSubscription::count();
        $vapidPublicKey = config('webpush.vapid.public_key');

        return response()->json([
            'subscribed' => $userSubscriptionsCount > 0,
            'user_subscriptions_count' => $userSubscriptionsCount,
            'total_system_subscriptions' => $totalSystemSubscriptions,
            'vapid_configured' => !empty($vapidPublicKey),
            'vapid_public_key' => $vapidPublicKey,
        ]);
    }

    /**
     * Kirim tes notifikasi ke perangkat user yang sedang login.
     */
    public function sendTest(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Sesi tidak valid.'], 401);
        }

        $subscriptionsCount = $user->pushSubscriptions()->count();
        if ($subscriptionsCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Perangkat Anda belum terdaftar untuk Push Notification. Silakan klik tombol "Izinkan & Daftarkan Perangkat Ini" terlebih dahulu.',
            ], 422);
        }

        $title = $request->input('title') ?: 'Tes Notifikasi Habit SDAM 🔔';
        $body = $request->input('body') ?: 'Alhamdulillah, Push Notification PWA telah aktif dan berhasil terkirim ke perangkat ini!';
        $url = $request->input('url') ?: route('dashboard');

        try {
            $user->notify(new GenericWebPushNotification($title, $body, $url));

            return response()->json([
                'success' => true,
                'message' => "Notifikasi berhasil dikirim ke {$subscriptionsCount} perangkat terdaftar Anda.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim push: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Kirim siaran push notification ke pegawai oleh Admin atau Superadmin.
     */
    public function sendBroadcast(Request $request)
    {
        $currentUser = Auth::user();
        if (!$currentUser || (!$currentUser->isActualSuperadmin() && !$currentUser->hasAnyRole(['admin', 'superadmin']))) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Admin dan Superadmin yang berhak menyiarkan notifikasi push.',
            ], 403);
        }

        $validated = $request->validate([
            'title'    => 'required|string|max:100',
            'body'     => 'required|string|max:255',
            'target'   => 'required|in:all,division,user',
            'division' => 'nullable|string|max:100',
            'user_id'  => 'nullable|exists:users,id',
            'url'      => 'nullable|string|max:255',
        ], [
            'title.required'  => 'Judul notifikasi wajib diisi.',
            'body.required'   => 'Isi pesan notifikasi wajib diisi.',
            'target.required' => 'Pilih target penerima notifikasi.',
        ]);

        $query = User::query();

        if ($validated['target'] === 'division' && !empty($validated['division'])) {
            $query->where('divisi', $validated['division']);
        } elseif ($validated['target'] === 'user' && !empty($validated['user_id'])) {
            $query->where('id', $validated['user_id']);
        }

        // Ambil semua target pengguna (tidak dibatasi hanya yang memiliki push subscription lagi)
        // karena sekarang notifikasi juga disimpan ke database in-app.
        $targetUsers = $query->with('pushSubscriptions')->get();

        if ($targetUsers->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada target pegawai yang cocok dengan filter yang dipilih.',
            ], 422);
        }

        $title = $validated['title'];
        $body  = $validated['body'];
        $url   = !empty($validated['url']) ? $validated['url'] : route('dashboard');

        $successUsers = 0;
        $totalDevices = 0;

        foreach ($targetUsers as $targetUser) {
            try {
                $targetUser->notify(new GenericWebPushNotification($title, $body, $url));
                $successUsers++;
                $totalDevices += $targetUser->pushSubscriptions->count();
            } catch (\Throwable $e) {
                Log::warning("Gagal mengirim notifikasi push ke user ID {$targetUser->id}: " . $e->getMessage());
            }
        }

        return response()->json([
            'success'      => true,
            'sent_users'   => $successUsers,
            'sent_devices' => $totalDevices,
            'message'      => "Notifikasi push berhasil disiarkan ke {$successUsers} pegawai ({$totalDevices} perangkat terdaftar).",
        ]);
    }
}
