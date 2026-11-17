<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;

use App\Models\HabitLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class QuranRecapController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $periode = $request->input('periode', 'bulan'); // bulan, semester, tahun
        $bulan = $request->input('bulan', Carbon::now()->month);
        $tahun = $request->input('tahun', Carbon::now()->year);
        $semester = $request->input('semester', (Carbon::now()->month <= 6 ? 1 : 2));

        $query = HabitLog::where('user_id', $user->id)
            ->whereHas('habit', function ($q) {
                $q->where('template', 'quran');
            })
            ->with('habit');

        if ($periode === 'bulan') {
            $start = Carbon::create($tahun, $bulan, 1)->startOfMonth()->format('Y-m-d');
            $end = Carbon::create($tahun, $bulan, 1)->endOfMonth()->format('Y-m-d');
            $query->whereBetween('tanggal', [$start, $end]);
        } elseif ($periode === 'semester') {
            $startMonth = $semester == 1 ? 1 : 7;
            $endMonth = $semester == 1 ? 6 : 12;
            $start = Carbon::create($tahun, $startMonth, 1)->startOfMonth()->format('Y-m-d');
            $end = Carbon::create($tahun, $endMonth, 1)->endOfMonth()->format('Y-m-d');
            $query->whereBetween('tanggal', [$start, $end]);
        } elseif ($periode === 'tahun') {
            $query->whereBetween('tanggal', ["{$tahun}-01-01", "{$tahun}-12-31"]);
        }

        $logs = $query->orderBy('tanggal', 'asc')->get();

        // Group by tanggal
        $groupedData = [];
        foreach ($logs as $log) {
            $date = Carbon::parse($log->tanggal)->format('Y-m-d');
            if (!isset($groupedData[$date])) {
                $groupedData[$date] = [
                    'tanggal' => $date,
                    'tilawah' => null,
                    'murojaah' => null,
                ];
            }

            // Determine if tilawah or murojaah based on habit name
            // As per seeder, Murojaah has name containing 'Murojaah'
            if (stripos($log->habit->nama_habit, 'murojaah') !== false) {
                $groupedData[$date]['murojaah'] = $log->details;
            } else {
                $groupedData[$date]['tilawah'] = $log->details;
            }
        }

        // Return array format
        $recapData = array_values($groupedData);

        return Inertia::render('QuranRecap', [
            'recapData' => $recapData,
            'filters' => [
                'periode' => $periode,
                'bulan' => (int) $bulan,
                'tahun' => (int) $tahun,
                'semester' => (int) $semester,
            ]
        ]);
    }
}
