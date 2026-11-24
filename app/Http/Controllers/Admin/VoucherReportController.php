<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\HabitVoucher;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VoucherReportController extends Controller
{
    /**
     * Tampilkan halaman kelola penerbitan & penukaran voucher habit koperasi.
     */
    public function index(Request $request)
    {
        $today = Carbon::today();
        $selectedPeriod = $request->query('period', $today->format('Y-m'));

        try {
            $periodDate = Carbon::parse($selectedPeriod . '-01');
        } catch (\Exception $e) {
            $periodDate = $today->copy()->startOfMonth();
            $selectedPeriod = $periodDate->format('Y-m');
        }

        $search = $request->query('search');
        $divisionFilter = $request->query('division');
        $statusFilter = $request->query('status'); // 'all', 'qualified', 'generated', 'redeemed', 'unredeemed'

        // 1. Ambil semua pegawai reguler (non-admin/superadmin)
        $userQuery = User::whereIn('id', User::getNonAdminUserIds())
            ->orderBy('name');

        if ($divisionFilter) {
            $userQuery->where('divisi', $divisionFilter);
        }

        if ($search) {
            $userQuery->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        $users = $userQuery->get();

        // 2. Ambil voucher yang sudah terbit di periode ini
        $existingVouchers = HabitVoucher::with(['redeemer', 'generator'])
            ->where('period', $selectedPeriod)
            ->get()
            ->keyBy('user_id');

        $reportData = [];
        $totalQualified = 0;
        $totalGenerated = 0;
        $totalRedeemed = 0;
        $totalUnredeemed = 0;

        foreach ($users as $u) {
            $stats = HabitVoucher::calculateUserAchievement($u, $selectedPeriod);
            $voucher = $existingVouchers->get($u->id);

            $isQualified = $stats['is_target_reached'];
            if ($isQualified) {
                $totalQualified++;
            }

            if ($voucher) {
                $totalGenerated++;
                if ($voucher->isRedeemed()) {
                    $totalRedeemed++;
                } else {
                    $totalUnredeemed++;
                }
            }

            $item = [
                'user_id'             => $u->id,
                'name'                => $u->name,
                'nip'                 => $u->nip ?: '-',
                'divisi'              => $u->divisi ?: 'Tanpa Divisi',
                'avatar'              => $u->avatar,
                'total_score'         => $stats['total_score'],
                'max_score'           => $stats['max_possible_score'],
                'achieved_percentage' => $stats['achieved_percentage'],
                'target_percentage'   => $stats['target_percentage'],
                'is_qualified'        => $isQualified,
                'voucher'             => $voucher ? [
                    'id'               => $voucher->id,
                    'code'             => $voucher->voucher_code,
                    'title'            => $voucher->title,
                    'reward_value'     => $voucher->reward_value,
                    'status'           => $voucher->status,
                    'is_redeemed'      => $voucher->isRedeemed(),
                    'claimed_at'       => $voucher->claimed_at ? $voucher->claimed_at->format('d M Y, H:i') : null,
                    'redeemed_by_name' => $voucher->redeemer?->name,
                    'generated_at'     => $voucher->generated_at ? $voucher->generated_at->format('d M Y, H:i') : null,
                    'valid_until'      => $voucher->valid_until ? $voucher->valid_until->format('d M Y') : null,
                ] : null,
            ];

            // Filter Status
            if ($statusFilter === 'qualified' && !$isQualified) continue;
            if ($statusFilter === 'not_qualified' && $isQualified) continue;
            if ($statusFilter === 'generated' && !$voucher) continue;
            if ($statusFilter === 'not_generated' && (!$isQualified || $voucher)) continue;
            if ($statusFilter === 'redeemed' && (!$voucher || !$voucher->isRedeemed())) continue;
            if ($statusFilter === 'unredeemed' && (!$voucher || $voucher->isRedeemed())) continue;

            $reportData[] = $item;
        }

        // Pengaturan voucher
        $settings = [
            'monthly_target_score' => (float) (Setting::get('monthly_target_score') ?? 80.0),
            'voucher_reward_title' => Setting::get('voucher_reward_title', 'Voucher Apresiasi Target Habit'),
            'voucher_reward_value' => Setting::get('voucher_reward_value', 'Voucher Belanja Koperasi Sekolah Rp 50.000'),
            'voucher_terms'        => Setting::get('voucher_terms', "1. Voucher berhak diperoleh pegawai yang memenuhi target habit bulanan sekolah.\n2. Tunjukkan kode voucher kepada petugas Koperasi Sekolah untuk penukaran barang / belanja.\n3. Voucher hanya dapat ditukarkan satu kali."),
        ];

        // Opsi periode 6 bulan terakhir
        $availablePeriods = [];
        for ($m = 0; $m < 6; $m++) {
            $p = $today->copy()->subMonths($m);
            $availablePeriods[] = [
                'period' => $p->format('Y-m'),
                'label'  => $p->locale('id')->isoFormat('MMMM Y'),
            ];
        }

        $divisions = Division::orderBy('name')->get();

        return Inertia::render('Admin/Vouchers/Index', [
            'period'           => $selectedPeriod,
            'periodName'       => $periodDate->locale('id')->isoFormat('MMMM Y'),
            'reportData'       => $reportData,
            'totalPegawai'     => count($users),
            'totalQualified'   => $totalQualified,
            'totalGenerated'   => $totalGenerated,
            'totalRedeemed'    => $totalRedeemed,
            'totalUnredeemed'  => $totalUnredeemed,
            'filters'          => [
                'search'   => $search,
                'division' => $divisionFilter,
                'status'   => $statusFilter ?: 'all',
            ],
            'settings'         => $settings,
            'availablePeriods' => $availablePeriods,
            'divisions'        => $divisions,
        ]);
    }

    /**
     * Generate voucher untuk pegawai terpilih atau seluruh pegawai yang memenuhi target.
     */
    public function generate(Request $request)
    {
        $admin = auth()->user();
        $period = $request->input('period', Carbon::today()->format('Y-m'));
        $userId = $request->input('user_id'); // Jika ada, generate perorangan

        if ($userId) {
            $user = User::findOrFail($userId);
            $voucher = HabitVoucher::generateForUser($user, $period, $admin);

            if (!$voucher) {
                return redirect()->back()->with('error', "Pegawai {$user->name} belum memenuhi target habit yang ditetapkan oleh sekolah.");
            }

            return redirect()->back()->with('success', "Voucher habit untuk {$user->name} berhasil diterbitkan!");
        }

        // Batch generate untuk semua pegawai yang memenuhi target di periode ini
        $users = User::whereIn('id', User::getNonAdminUserIds())->get();
        $count = 0;

        foreach ($users as $user) {
            $voucher = HabitVoucher::generateForUser($user, $period, $admin);
            if ($voucher && $voucher->wasRecentlyCreated) {
                $count++;
            }
        }

        return redirect()->back()->with('success', "Berhasil menerbitkan {$count} voucher habit untuk pegawai yang memenuhi target sekolah periode {$period}.");
    }

    /**
     * Tandai voucher sebagai SUDAH DITUKARKAN di Koperasi Sekolah.
     */
    public function redeem(Request $request)
    {
        $voucherId = $request->input('voucher_id');
        $voucherCode = trim((string) $request->input('voucher_code'));
        $notes = $request->input('notes', 'Ditukarkan di Koperasi Sekolah');

        $query = HabitVoucher::query();
        if ($voucherId) {
            $query->where('id', $voucherId);
        } elseif ($voucherCode) {
            $query->where('voucher_code', $voucherCode);
        } else {
            return redirect()->back()->with('error', 'Kode voucher atau ID voucher wajib diisi.');
        }

        $voucher = $query->first();

        if (!$voucher) {
            return redirect()->back()->with('error', "Voucher dengan kode '{$voucherCode}' tidak ditemukan di sistem.");
        }

        if ($voucher->isRedeemed()) {
            return redirect()->back()->with('warning', "Voucher {$voucher->voucher_code} sudah pernah ditukarkan pada " . ($voucher->claimed_at ? $voucher->claimed_at->format('d M Y H:i') : '') . ".");
        }

        $admin = auth()->user();
        $voucher->update([
            'status'           => 'claimed',
            'claimed_at'       => now(),
            'redeemed_by'      => $admin->id,
            'redemption_notes' => $notes,
        ]);

        return redirect()->back()->with('success', "Voucher {$voucher->voucher_code} milik {$voucher->user?->name} berhasil ditandai SUDAH DITUKARKAN di Koperasi Sekolah!");
    }

    /**
     * Batalkan status penukaran voucher (kembalikan ke BELUM DITUKARKAN).
     */
    public function unredeem(Request $request, HabitVoucher $voucher)
    {
        $voucher->update([
            'status'           => 'active',
            'claimed_at'       => null,
            'redeemed_by'      => null,
            'redemption_notes' => null,
        ]);

        return redirect()->back()->with('success', "Status voucher {$voucher->voucher_code} berhasil dikembalikan menjadi BELUM DITUKARKAN.");
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

        return redirect()->back()->with('success', 'Pengaturan reward voucher koperasi berhasil disimpan!');
    }
}
