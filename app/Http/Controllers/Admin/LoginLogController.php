<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoginLogController extends Controller
{
    /**
     * Tampilkan daftar log login user dengan filter bulanan dan pencarian.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $month = $request->query('month', Carbon::now()->format('Y-m'));

        $query = LoginLog::with('user:id,name,email');

        // Filter berdasarkan bulan (format YYYY-MM) jika ada
        if (!empty($month)) {
            try {
                $startOfMonth = Carbon::parse($month)->startOfMonth();
                $endOfMonth   = Carbon::parse($month)->endOfMonth();
                $query->whereBetween('created_at', [$startOfMonth, $endOfMonth]);
            } catch (\Exception $e) {
                // Abaikan jika format bulan tidak valid
            }
        }

        // Filter pencarian nama user, email, IP, atau lokasi
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhere('ip_address', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%");
            });
        }

        // Statistik ringkas periode ini
        $stats = [
            'total_logins' => (clone $query)->count(),
            'unique_users' => (clone $query)->distinct('user_id')->count('user_id'),
        ];

        $logs = $query->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        $formattedMonth = !empty($month) 
            ? Carbon::parse($month)->locale('id')->isoFormat('MMMM Y') 
            : 'Semua Periode';

        return Inertia::render('Admin/LoginLogs/Index', [
            'logs' => $logs,
            'filters' => [
                'search' => $search,
                'month'  => $month,
            ],
            'stats' => $stats,
            'formattedMonth' => $formattedMonth,
        ]);
    }
}
