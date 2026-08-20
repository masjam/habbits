<?php

namespace App\Http\Controllers;

use App\Models\HabitLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class KajianController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $selectedMonth = $request->query('month', Carbon::now()->format('m'));
        $selectedYear = $request->query('year', Carbon::now()->format('Y'));
        
        $startDate = Carbon::createFromDate($selectedYear, $selectedMonth, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $logs = HabitLog::with('habit:id,nama_habit')
            ->where('user_id', $user->id)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->whereHas('habit', function ($q) {
                $q->whereIn('template', ['hadist', 'buku']);
            })
            ->whereNotNull('details')
            ->orderBy('tanggal', 'desc')
            ->get(['id', 'habit_id', 'tanggal', 'details']);

        // Filter collection to only include items where 'isi' or 'catatan' is not empty
        $catatan = $logs->filter(function ($log) {
            $details = $log->details;
            if (!is_array($details)) return false;
            
            $isi = $details['isi'] ?? $details['catatan'] ?? '';
            return trim($isi) !== '';
        })->values();

        $months = [
            ['value' => '01', 'label' => 'Januari'],
            ['value' => '02', 'label' => 'Februari'],
            ['value' => '03', 'label' => 'Maret'],
            ['value' => '04', 'label' => 'April'],
            ['value' => '05', 'label' => 'Mei'],
            ['value' => '06', 'label' => 'Juni'],
            ['value' => '07', 'label' => 'Juli'],
            ['value' => '08', 'label' => 'Agustus'],
            ['value' => '09', 'label' => 'September'],
            ['value' => '10', 'label' => 'Oktober'],
            ['value' => '11', 'label' => 'November'],
            ['value' => '12', 'label' => 'Desember'],
        ];

        $currentYear = Carbon::now()->year;
        $years = [$currentYear - 1, $currentYear, $currentYear + 1];

        return Inertia::render('Kajian/Index', [
            'catatan' => $catatan,
            'filters' => [
                'month' => $selectedMonth,
                'year' => $selectedYear,
            ],
            'months' => $months,
            'years' => $years
        ]);
    }
}
