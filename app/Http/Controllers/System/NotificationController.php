<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    /**
     * Tampilkan halaman daftar semua notifikasi (Pesan).
     */
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->paginate(15);
        
        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * Tandai satu notifikasi sebagai telah dibaca.
     */
    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }

        return back();
    }

    /**
     * Tandai semua notifikasi sebagai telah dibaca.
     */
    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return back();
    }
}
