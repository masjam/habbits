<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoginLogController extends Controller
{
    /**
     * Tampilkan daftar log login user.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');

        $logs = LoginLog::with('user:id,name,email,role')
            ->when($search, function ($query, $search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhere('ip_address', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/LoginLogs/Index', [
            'logs' => $logs,
            'filters' => [
                'search' => $search
            ]
        ]);
    }
}
