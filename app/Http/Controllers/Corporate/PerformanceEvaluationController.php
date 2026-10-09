<?php

namespace App\Http\Controllers\Corporate;

use App\Http\Controllers\Controller;

use App\Models\PerformanceEvaluation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;

class PerformanceEvaluationController extends Controller
{
    /**
     * Tampilan untuk Admin (Daftar Pegawai dan Nilai)
     */
    public function adminIndex(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['superadmin', 'admin'])) {
            abort(403, 'Unauthorized action.');
        }

        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);

        // Ambil data user beserta nilainya di bulan & tahun yang dipilih
        $users = User::whereHas('roles', function ($q) {
                $q->where('name', 'user'); // hanya ambil pegawai
            })
            ->with(['performanceEvaluations' => function ($q) use ($month, $year) {
                $q->where('evaluation_month', $month)
                  ->where('evaluation_year', $year);
            }])
            ->get();

        // Transform data agar mudah dibaca di frontend
        $data = $users->map(function ($u) {
            $eval = $u->performanceEvaluations->first();
            return [
                'user_id' => $u->id,
                'name' => $u->name,
                'nip' => $u->nip,
                'divisi' => $u->divisi,
                'evaluation' => $eval ? [
                    'id' => $eval->id,
                    'score_pedagogic' => $eval->score_pedagogic,
                    'score_professional' => $eval->score_professional,
                    'score_personality' => $eval->score_personality,
                    'score_social' => $eval->score_social,
                    'average_score' => $eval->average_score,
                    'notes' => $eval->notes,
                ] : null
            ];
        });

        return Inertia::render('Admin/Performance/Index', [
            'employees' => $data,
            'selectedMonth' => (int) $month,
            'selectedYear' => (int) $year
        ]);
    }

    /**
     * Simpan atau Update Nilai
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['superadmin', 'admin'])) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'evaluation_month' => 'required|integer|min:1|max:12',
            'evaluation_year' => 'required|integer',
            'score_pedagogic' => 'required|numeric|min:0|max:100',
            'score_professional' => 'required|numeric|min:0|max:100',
            'score_personality' => 'required|numeric|min:0|max:100',
            'score_social' => 'required|numeric|min:0|max:100',
            'notes' => 'nullable|string'
        ]);

        $average = ($request->score_pedagogic + $request->score_professional + $request->score_personality + $request->score_social) / 4;

        PerformanceEvaluation::updateOrCreate(
            [
                'user_id' => $request->user_id,
                'evaluation_month' => $request->evaluation_month,
                'evaluation_year' => $request->evaluation_year,
            ],
            [
                'evaluator_id' => $user->id,
                'score_pedagogic' => $request->score_pedagogic,
                'score_professional' => $request->score_professional,
                'score_personality' => $request->score_personality,
                'score_social' => $request->score_social,
                'average_score' => $average,
                'notes' => $request->notes,
            ]
        );

        return redirect()->back()->with('success', 'Penilaian kinerja berhasil disimpan.');
    }

    /**
     * Tampilan untuk User / Pegawai (Rapor Kinerja Sendiri)
     */
    public function userIndex(Request $request)
    {
        $user = Auth::user();
        $year = $request->input('year', Carbon::now()->year);

        $evaluations = PerformanceEvaluation::where('user_id', $user->id)
            ->where('evaluation_year', $year)
            ->orderBy('evaluation_month', 'asc')
            ->get();

        return Inertia::render('Employee/Performance/Index', [
            'evaluations' => $evaluations,
            'selectedYear' => (int) $year
        ]);
    }
}
