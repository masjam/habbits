<?php

namespace App\Queries;

use App\Models\Division;
use App\Models\Habit;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class HabitReportQuery
{
    public function resolveContext(array $filters): array
    {
        $now = Carbon::now('Asia/Jakarta');
        $month = (int) ($filters['month'] ?? $now->month);
        $year = (int) ($filters['year'] ?? $now->year);

        $date = Carbon::createFromDate($year, $month, 1, 'Asia/Jakarta');
        $startOfMonth = $date->copy()->startOfMonth()->toDateString();
        $endOfMonth = $date->copy()->endOfMonth()->toDateString();
        $daysInMonth = $date->daysInMonth;

        $settingTargetValue = Setting::get('monthly_target_score');
        $targetBulanan = $settingTargetValue !== null ? (float) $settingTargetValue : 80.0;

        $dailyMaxScore = Habit::allActiveCached()
            ->where('is_pengganti_haid', false)
            ->sum('skor_maksimal') ?: 100;
        $skorMaksimalSebulan = (int) ($dailyMaxScore * $daysInMonth);

        return [
            'date' => $date,
            'month' => $month,
            'year' => $year,
            'startOfMonth' => $startOfMonth,
            'endOfMonth' => $endOfMonth,
            'daysInMonth' => $daysInMonth,
            'targetBulanan' => $targetBulanan,
            'skorMaksimalSebulan' => $skorMaksimalSebulan,
            'namaBulan' => $date->locale('id')->translatedFormat('F Y'),
        ];
    }

    public function baseQuery(array $filters, ?array $ctx = null): Builder
    {
        $ctx ??= $this->resolveContext($filters);
        $startOfMonth = $ctx['startOfMonth'];
        $endOfMonth = $ctx['endOfMonth'];
        $skorMaksimalSebulan = $ctx['skorMaksimalSebulan'];
        $targetBulanan = $ctx['targetBulanan'];

        $search = $filters['search'] ?? null;
        $divisi = $filters['divisi'] ?? null;
        $kategoriSkor = $filters['kategori_skor'] ?? null;
        $sort = $filters['sort'] ?? 'skor';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = User::whereIn('id', User::getNonAdminUserIds())
            ->select(['id', 'name', 'nip', 'gender', 'divisi', 'status_kehadiran', 'personal_target', 'target_tidak_aktif'])
            ->with('badges')
            ->when($search, fn (Builder $q, $s) => $q->where(fn ($sub) => $sub->where('name', 'like', "%{$s}%")->orWhere('nip', 'like', "%{$s}%")))
            ->when($divisi, fn (Builder $q, $d) => $q->where('divisi', $d))
            ->withSum(['habitLogs' => fn ($q) => $q->whereBetween('tanggal', [$startOfMonth, $endOfMonth])], 'skor_diperoleh')
            ->withMax(['habitLogs as last_filled_at' => fn ($q) => $q->whereBetween('tanggal', [$startOfMonth, $endOfMonth])], 'tanggal');

        if ($kategoriSkor) {
            $divisions = Division::whereNotNull('target_divisi')->get(['name', 'target_divisi']);
            $bindings = [];
            $divisionCaseSql = 'NULL';
            if ($divisions->isNotEmpty()) {
                $whens = [];
                foreach ($divisions as $div) {
                    $whens[] = 'WHEN users.divisi = ? THEN ?';
                    $bindings[] = $div->name;
                    $bindings[] = (float) $div->target_divisi;
                }
                $divisionCaseSql = 'CASE ' . implode(' ', $whens) . ' ELSE NULL END';
            }

            $userTargetSql = "COALESCE(CASE WHEN status_kehadiran != 'Aktif' THEN target_tidak_aktif ELSE NULL END, {$divisionCaseSql}, ?)";
            $bindings[] = $targetBulanan;

            $scoreSql = '(SELECT COALESCE(SUM(skor_diperoleh), 0) FROM habit_logs WHERE habit_logs.user_id = users.id AND tanggal BETWEEN ? AND ?)';

            if ($kategoriSkor === '0-30') {
                $query->whereRaw("{$scoreSql} <= (0.30 * ?)", [$startOfMonth, $endOfMonth, $skorMaksimalSebulan]);
            } elseif ($kategoriSkor === '30-50') {
                $query->whereRaw("{$scoreSql} > (0.30 * ?) AND {$scoreSql} <= (0.50 * ?)", [
                    $startOfMonth, $endOfMonth, $skorMaksimalSebulan,
                    $startOfMonth, $endOfMonth, $skorMaksimalSebulan,
                ]);
            } elseif ($kategoriSkor === '50-target') {
                $query->whereRaw(
                    "{$scoreSql} > (0.50 * ?) AND {$scoreSql} < (({$userTargetSql}) / 100 * ?)",
                    array_merge(
                        [$startOfMonth, $endOfMonth, $skorMaksimalSebulan, $startOfMonth, $endOfMonth],
                        $bindings,
                        [$skorMaksimalSebulan]
                    )
                );
            } elseif ($kategoriSkor === 'tercapai') {
                $query->whereRaw(
                    "{$scoreSql} >= (({$userTargetSql}) / 100 * ?)",
                    array_merge([$startOfMonth, $endOfMonth], $bindings, [$skorMaksimalSebulan])
                );
            }
        }

        if ($sort === 'name') {
            $query->orderBy('name', $dir);
        } else {
            $query->orderByRaw("COALESCE(habit_logs_sum_skor_diperoleh, 0) {$dir}")->orderBy('name', 'asc');
        }

        return $query;
    }

    public function summary(array $filters, ?array $ctx = null): array
    {
        $ctx ??= $this->resolveContext($filters);
        $startOfMonth = $ctx['startOfMonth'];
        $endOfMonth = $ctx['endOfMonth'];
        $max = $ctx['skorMaksimalSebulan'];
        $globalTarget = $ctx['targetBulanan'];

        $nonAdminIds = User::getNonAdminUserIds();
        if (empty($nonAdminIds)) {
            return [
                'total_employees' => 0,
                'filled_employees' => 0,
                'unfilled_employees' => 0,
                'target_reached_count' => 0,
                'average_percent' => 0.0,
            ];
        }

        $divisi = $filters['divisi'] ?? null;
        $search = $filters['search'] ?? null;

        $rows = DB::table('users')
            ->whereIn('users.id', $nonAdminIds)
            ->when($search, fn ($q, $s) => $q->where(fn ($sub) => $sub->where('users.name', 'like', "%{$s}%")->orWhere('users.nip', 'like', "%{$s}%")))
            ->when($divisi, fn ($q, $d) => $q->where('users.divisi', $d))
            ->leftJoin('habit_logs', function ($join) use ($startOfMonth, $endOfMonth) {
                $join->on('users.id', '=', 'habit_logs.user_id')
                     ->whereBetween('habit_logs.tanggal', [$startOfMonth, $endOfMonth]);
            })
            ->groupBy('users.id', 'users.divisi', 'users.status_kehadiran', 'users.target_tidak_aktif')
            ->selectRaw('users.id, users.divisi, users.status_kehadiran, users.target_tidak_aktif, COALESCE(SUM(habit_logs.skor_diperoleh), 0) as total_score')
            ->get();

        $divTargets = Division::getTargetsMap();
        $total = $rows->count();
        $filled = 0;
        $reached = 0;
        $sumPct = 0.0;

        foreach ($rows as $r) {
            $score = (float) $r->total_score;
            if ($score > 0) {
                $filled++;
            }
            $pct = $max > 0 ? ($score / $max) * 100 : 0.0;
            $sumPct += $pct;

            $target = $globalTarget;
            if ($r->status_kehadiran !== 'Aktif' && !is_null($r->target_tidak_aktif)) {
                $target = (float) $r->target_tidak_aktif;
            } elseif (!empty($r->divisi) && isset($divTargets[$r->divisi])) {
                $target = (float) $divTargets[$r->divisi];
            }

            if ($pct >= $target && $score > 0) {
                $reached++;
            }
        }

        return [
            'total_employees' => $total,
            'filled_employees' => $filled,
            'unfilled_employees' => max(0, $total - $filled),
            'target_reached_count' => $reached,
            'average_percent' => $total > 0 ? round($sumPct / $total, 1) : 0.0,
        ];
    }
}
