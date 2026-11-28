<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VoucherFilterRequest;
use App\Http\Requests\Admin\VoucherGenerateRequest;
use App\Http\Requests\Admin\VoucherRedeemRequest;
use App\Models\Division;
use App\Models\Habit;
use App\Models\HabitVoucher;
use App\Models\HabitVoucherLog;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class VoucherReportController extends Controller
{
    /**
     * Tampilkan halaman kelola penerbitan & penukaran voucher habit koperasi (tanpa N+1, pagination server-side).
     */
    public function index(VoucherFilterRequest $request)
    {
        $filters = $request->validatedFilters();
        $today = Carbon::today('Asia/Jakarta');
        $selectedPeriod = $filters['period'];

        try {
            $periodDate = Carbon::parse($selectedPeriod . '-01', 'Asia/Jakarta');
        } catch (\Throwable) {
            $periodDate = $today->copy()->startOfMonth();
            $selectedPeriod = $periodDate->format('Y-m');
        }

        $startOfMonth = $periodDate->copy()->startOfMonth()->toDateString();
        $endOfMonth = $periodDate->copy()->endOfMonth()->toDateString();
        $daysInMonth = $periodDate->daysInMonth;

        $dailyMax = Habit::allActiveCached()
            ->where('is_pengganti_haid', false)
            ->sum('skor_maksimal') ?: 100;
        $maxPossibleScore = (int) ($dailyMax * $daysInMonth);

        $settingTarget = Setting::get('monthly_target_score');
        $globalTarget = $settingTarget !== null ? (float) $settingTarget : 80.0;
        $divisionTargets = Division::getTargetsMap();

        $search = $filters['search'];
        $divisionFilter = $filters['division'];
        $statusFilter = $filters['status'];

        // 1 query untuk seluruh pegawai aktif + agregat skor bulan terpilih (anti-N+1)
        $users = User::whereIn('id', User::getNonAdminUserIds())
            ->select(['id', 'name', 'nip', 'divisi', 'avatar', 'status_kehadiran', 'personal_target', 'target_tidak_aktif'])
            ->when($divisionFilter, fn ($q, $d) => $q->where('divisi', $d))
            ->when($search, fn ($q, $s) => $q->where(function ($sub) use ($s) {
                $sub->where('name', 'like', "%{$s}%")
                    ->orWhere('nip', 'like', "%{$s}%")
                    ->orWhereHas('habitVouchers', fn ($vq) => $vq->where('voucher_code', 'like', "%{$s}%"));
            }))
            ->withSum(['habitLogs' => fn ($q) => $q->whereBetween('tanggal', [$startOfMonth, $endOfMonth])], 'skor_diperoleh')
            ->orderBy('name')
            ->get();

        // 1 query untuk seluruh voucher periode ini beserta relasi & audit logs
        $existingVouchers = HabitVoucher::with(['redeemer:id,name', 'generator:id,name', 'logs.actor:id,name'])
            ->where('period', $selectedPeriod)
            ->get()
            ->keyBy('user_id');

        $totalQualified = 0;
        $totalGenerated = 0;
        $totalRedeemed = 0;
        $totalUnredeemed = 0;
        $totalExpired = 0;
        $totalValueGenerated = 0.0;
        $totalValueRedeemed = 0.0;

        $filteredItems = collect();

        foreach ($users as $u) {
            $totalScore = (int) ($u->habit_logs_sum_skor_diperoleh ?? 0);
            $achievedPct = $maxPossibleScore > 0 ? round(($totalScore / $maxPossibleScore) * 100, 1) : 0.0;

            $targetPct = $globalTarget;
            if ($u->status_kehadiran !== 'Aktif' && !is_null($u->target_tidak_aktif)) {
                $targetPct = (float) $u->target_tidak_aktif;
            } elseif (!is_null($u->personal_target)) {
                $targetPct = (float) $u->personal_target;
            } elseif (!empty($u->divisi) && isset($divisionTargets[$u->divisi])) {
                $targetPct = (float) $divisionTargets[$u->divisi];
            }

            $isQualified = ($achievedPct >= $targetPct) && ($totalScore > 0);
            $voucher = $existingVouchers->get($u->id);

            if ($isQualified) {
                $totalQualified++;
            }

            if ($voucher) {
                $totalGenerated++;
                $nominal = $voucher->getRewardNumericValue();
                $totalValueGenerated += $nominal;

                if ($voucher->isRedeemed()) {
                    $totalRedeemed++;
                    $totalValueRedeemed += $nominal;
                } elseif ($voucher->isExpired()) {
                    $totalExpired++;
                } else {
                    $totalUnredeemed++;
                }
            }

            if ($statusFilter === 'qualified' && !$isQualified) continue;
            if ($statusFilter === 'not_qualified' && $isQualified) continue;
            if ($statusFilter === 'generated' && !$voucher) continue;
            if ($statusFilter === 'not_generated' && (!$isQualified || $voucher)) continue;
            if ($statusFilter === 'redeemed' && (!$voucher || !$voucher->isRedeemed())) continue;
            if ($statusFilter === 'unredeemed' && (!$voucher || !$voucher->isUnredeemed())) continue;
            if ($statusFilter === 'expired' && (!$voucher || !$voucher->isExpired())) continue;

            $filteredItems->push([
                'user_id'             => $u->id,
                'name'                => $u->name,
                'nip'                 => $u->nip ?: '-',
                'divisi'              => $u->divisi ?: 'Tanpa Divisi',
                'avatar'              => $u->avatar,
                'total_score'         => $totalScore,
                'max_score'           => $maxPossibleScore,
                'achieved_percentage' => $achievedPct,
                'target_percentage'   => $targetPct,
                'is_qualified'        => $isQualified,
                'voucher'             => $voucher ? [
                    'id'               => $voucher->id,
                    'code'             => $voucher->voucher_code,
                    'title'            => $voucher->title,
                    'reward_value'     => $voucher->reward_value,
                    'nominal'          => $voucher->getRewardNumericValue(),
                    'status'           => $voucher->isRedeemed() ? 'claimed' : ($voucher->isExpired() ? 'expired' : 'active'),
                    'is_redeemed'      => $voucher->isRedeemed(),
                    'is_expired'       => $voucher->isExpired(),
                    'transaction_code' => $voucher->transaction_code,
                    'claimed_at'       => $voucher->claimed_at?->timezone('Asia/Jakarta')->format('d M Y, H:i'),
                    'redeemed_by_name' => $voucher->redeemer?->name,
                    'redemption_notes' => $voucher->redemption_notes,
                    'generated_by_name'=> $voucher->generator?->name,
                    'generated_at'     => $voucher->generated_at?->timezone('Asia/Jakarta')->format('d M Y, H:i'),
                    'valid_until'      => $voucher->valid_until?->format('d M Y'),
                    'logs'             => $voucher->logs->take(5)->map(fn ($l) => [
                        'id' => $l->id,
                        'action' => $l->action,
                        'actor' => $l->actor?->name ?? 'Sistem',
                        'notes' => $l->notes,
                        'at' => $l->created_at?->timezone('Asia/Jakarta')->format('d M Y, H:i'),
                    ])->values()->all(),
                ] : null,
            ]);
        }

        $page = Paginator::resolveCurrentPage();
        $perPage = $filters['per_page'];
        $paginated = (new LengthAwarePaginator(
            $filteredItems->forPage($page, $perPage)->values(),
            $filteredItems->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        ))->withQueryString();

        $settings = [
            'monthly_target_score' => $globalTarget,
            'voucher_reward_title' => Setting::get('voucher_reward_title', 'Voucher Apresiasi Target Habit'),
            'voucher_reward_value' => Setting::get('voucher_reward_value', 'Voucher Belanja Koperasi Sekolah Rp 50.000'),
            'voucher_terms'        => Setting::get('voucher_terms', "1. Voucher berhak diperoleh pegawai yang memenuhi target habit bulanan sekolah.\n2. Tunjukkan kode voucher kepada petugas Koperasi Sekolah untuk penukaran barang / belanja.\n3. Voucher hanya dapat ditukarkan satu kali."),
        ];

        $availablePeriods = [];
        for ($m = 0; $m < 6; $m++) {
            $p = $today->copy()->subMonths($m);
            $availablePeriods[] = [
                'period' => $p->format('Y-m'),
                'label'  => $p->locale('id')->isoFormat('MMMM Y'),
            ];
        }

        return Inertia::render('Admin/Vouchers/Index', [
            'period'              => $selectedPeriod,
            'periodName'          => $periodDate->locale('id')->isoFormat('MMMM Y'),
            'reportData'          => $paginated,
            'totalPegawai'        => $users->count(),
            'totalQualified'      => $totalQualified,
            'totalGenerated'      => $totalGenerated,
            'totalRedeemed'       => $totalRedeemed,
            'totalUnredeemed'     => $totalUnredeemed,
            'totalExpired'        => $totalExpired,
            'totalValueGenerated' => $totalValueGenerated,
            'totalValueRedeemed'  => $totalValueRedeemed,
            'filters'             => $filters,
            'settings'            => $settings,
            'availablePeriods'    => $availablePeriods,
            'divisions'           => Division::orderBy('name')->get(),
        ]);
    }

    /**
     * Generate voucher untuk pegawai terpilih atau seluruh pegawai yang memenuhi target (dalam transaksi).
     */
    public function generate(VoucherGenerateRequest $request)
    {
        $admin = auth()->user();
        $period = $request->input('period') ?: Carbon::today('Asia/Jakarta')->format('Y-m');
        $userId = $request->input('user_id');

        if ($userId) {
            $user = User::findOrFail($userId);
            $voucher = DB::transaction(function () use ($user, $period, $admin) {
                $v = HabitVoucher::generateForUser($user, $period, $admin);
                if ($v && $v->wasRecentlyCreated) {
                    HabitVoucherLog::create([
                        'habit_voucher_id' => $v->id,
                        'actor_id' => $admin->id,
                        'action' => 'generated',
                        'notes' => "Diterbitkan untuk periode {$period}",
                        'meta' => ['nominal' => $v->getRewardNumericValue()],
                        'created_at' => now(),
                    ]);
                }
                return $v;
            });

            if (!$voucher) {
                return redirect()->back()->with('error', "Pegawai {$user->name} belum memenuhi target habit yang ditetapkan oleh sekolah.");
            }

            return redirect()->back()->with('success', "Voucher habit untuk {$user->name} berhasil diterbitkan!");
        }

        $users = User::whereIn('id', User::getNonAdminUserIds())->get();
        $count = 0;

        DB::transaction(function () use ($users, $period, $admin, &$count) {
            foreach ($users as $user) {
                $voucher = HabitVoucher::generateForUser($user, $period, $admin);
                if ($voucher && $voucher->wasRecentlyCreated) {
                    $count++;
                    HabitVoucherLog::create([
                        'habit_voucher_id' => $voucher->id,
                        'actor_id' => $admin->id,
                        'action' => 'generated',
                        'notes' => "Penerbitan massal periode {$period}",
                        'meta' => ['nominal' => $voucher->getRewardNumericValue()],
                        'created_at' => now(),
                    ]);
                }
            }
        });

        return redirect()->back()->with('success', "Berhasil menerbitkan {$count} voucher habit untuk pegawai yang memenuhi target sekolah periode {$period}.");
    }

    /**
     * Tandai voucher sebagai SUDAH DITUKARKAN secara atomik (lockForUpdate, idempotent, tolak kedaluwarsa).
     */
    public function redeem(VoucherRedeemRequest $request)
    {
        $voucherId = $request->input('voucher_id');
        $voucherCode = trim((string) $request->input('voucher_code'));
        $notes = $request->input('notes') ?: 'Ditukarkan di Koperasi Sekolah';
        $admin = auth()->user();

        $result = DB::transaction(function () use ($voucherId, $voucherCode, $notes, $admin) {
            $query = HabitVoucher::query();
            if ($voucherId) {
                $query->where('id', $voucherId);
            } else {
                $query->where('voucher_code', $voucherCode);
            }

            $voucher = $query->lockForUpdate()->first();
            if (!$voucher) {
                return ['type' => 'error', 'message' => "Voucher dengan kode '{$voucherCode}' tidak ditemukan di sistem."];
            }

            if ($voucher->isRedeemed()) {
                return [
                    'type' => 'warning',
                    'message' => "Voucher {$voucher->voucher_code} sudah pernah ditukarkan pada " . ($voucher->claimed_at ? $voucher->claimed_at->format('d M Y H:i') : '') . '.',
                ];
            }

            if ($voucher->isExpired()) {
                return [
                    'type' => 'error',
                    'message' => "Voucher {$voucher->voucher_code} sudah kedaluwarsa dan tidak dapat ditebus.",
                ];
            }

            $voucher->update([
                'status'           => 'claimed',
                'claimed_at'       => now(),
                'redeemed_by'      => $admin->id,
                'redemption_notes' => $notes,
            ]);

            HabitVoucherLog::create([
                'habit_voucher_id' => $voucher->id,
                'actor_id' => $admin->id,
                'action' => 'redeemed',
                'notes' => $notes,
                'meta' => ['nominal' => $voucher->getRewardNumericValue()],
                'created_at' => now(),
            ]);

            return [
                'type' => 'success',
                'message' => "Voucher {$voucher->voucher_code} milik {$voucher->user?->name} berhasil ditandai SUDAH DITUKARKAN di Koperasi Sekolah!",
            ];
        });

        return redirect()->back()->with($result['type'], $result['message']);
    }

    /**
     * Batalkan status penukaran voucher secara atomik (tolak jika sudah dipakai di transaksi POS).
     */
    public function unredeem(Request $request, HabitVoucher $voucher)
    {
        $admin = auth()->user();

        $result = DB::transaction(function () use ($voucher, $admin) {
            $locked = HabitVoucher::where('id', $voucher->id)->lockForUpdate()->firstOrFail();

            if (!empty($locked->transaction_code)) {
                return [
                    'type' => 'error',
                    'message' => "Voucher {$locked->voucher_code} sudah terikat pada transaksi POS ({$locked->transaction_code}) dan tidak dapat dibatalkan.",
                ];
            }

            if (!$locked->isRedeemed()) {
                return [
                    'type' => 'warning',
                    'message' => "Voucher {$locked->voucher_code} belum berstatus ditebus.",
                ];
            }

            $locked->update([
                'status'           => 'active',
                'claimed_at'       => null,
                'redeemed_by'      => null,
                'redemption_notes' => null,
            ]);

            HabitVoucherLog::create([
                'habit_voucher_id' => $locked->id,
                'actor_id' => $admin->id,
                'action' => 'unredeemed',
                'notes' => 'Pembatalan status penukaran oleh admin',
                'created_at' => now(),
            ]);

            return [
                'type' => 'success',
                'message' => "Status voucher {$locked->voucher_code} berhasil dikembalikan menjadi BELUM DITUKARKAN.",
            ];
        });

        return redirect()->back()->with($result['type'], $result['message']);
    }

    /**
     * Update pengaturan reward voucher oleh Admin.
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'monthly_target_score' => 'required|numeric|min:0|max:100',
            'voucher_reward_title' => 'required|string|max:255',
            'voucher_reward_value' => 'required|string|max:255',
            'voucher_terms'        => 'nullable|string',
        ]);

        Setting::updateOrCreate(['key' => 'monthly_target_score'], ['value' => (string) $request->monthly_target_score]);
        Setting::updateOrCreate(['key' => 'voucher_reward_title'], ['value' => $request->voucher_reward_title]);
        Setting::updateOrCreate(['key' => 'voucher_reward_value'], ['value' => $request->voucher_reward_value]);
        if ($request->has('voucher_terms')) {
            Setting::updateOrCreate(['key' => 'voucher_terms'], ['value' => $request->voucher_terms]);
        }
        Setting::clearCache();

        return redirect()->back()->with('success', 'Pengaturan reward voucher koperasi berhasil disimpan!');
    }
}
