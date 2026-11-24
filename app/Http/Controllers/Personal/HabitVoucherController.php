<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Models\HabitVoucher;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class HabitVoucherController extends Controller
{
    /**
     * Tampilkan halaman Voucher Apresiasi Habit Pegawai.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // 1. Tentukan periode yang dipilih (format: YYYY-MM)
        $today = Carbon::today();
        $selectedPeriod = $request->query('period', $today->format('Y-m'));

        try {
            $periodDate = Carbon::parse($selectedPeriod . '-01');
        } catch (\Exception $e) {
            $periodDate = $today->copy()->startOfMonth();
            $selectedPeriod = $periodDate->format('Y-m');
        }

        // 2. Hitung pencapaian habit pegawai membaca pengaturan yang dibuat oleh Admin
        $achievement = HabitVoucher::calculateUserAchievement($user, $selectedPeriod);

        // Sisa hari dalam bulan berjalan
        $isCurrentMonth = ($today->format('Y-m') === $selectedPeriod);
        $daysRemaining = $isCurrentMonth ? max(0, $periodDate->daysInMonth - $today->day) : 0;

        // 3. Ambil voucher yang telah DITERBITKAN OLEH ADMIN untuk periode ini
        $currentVoucher = HabitVoucher::with(['redeemer', 'generator'])
            ->where('user_id', $user->id)
            ->where('period', $selectedPeriod)
            ->first();

        // 4. Ambil seluruh riwayat voucher yang pernah diterbitkan admin untuk pegawai ini
        $voucherHistory = HabitVoucher::with(['redeemer', 'generator'])
            ->where('user_id', $user->id)
            ->orderBy('period', 'desc')
            ->get();

        // 5. Pengaturan Reward Voucher Koperasi dari Sekolah
        $schoolSettings = [
            'reward_title'       => Setting::get('voucher_reward_title', 'Voucher Apresiasi Target Habit'),
            'reward_value'       => Setting::get('voucher_reward_value', 'Voucher Belanja Koperasi Sekolah Rp 50.000'),
            'reward_description' => Setting::get('voucher_reward_description', 'Penghargaan resmi atas pencapaian dan kedisiplinan pembiasaan ibadah harian. Dapat ditukarkan langsung di Koperasi Sekolah.'),
            'redeem_location'    => 'Koperasi Sekolah SD Muhammadiyah 1 Krian',
            'terms'              => Setting::get('voucher_terms', "1. Voucher berhak diperoleh bagi seluruh pegawai yang memenuhi target habit yang ditetapkan oleh sekolah.\n2. Voucher diterbitkan secara resmi oleh Admin / Manajemen Sekolah.\n3. Voucher dapat ditukarkan langsung ke bagian Koperasi Sekolah dengan menunjukkan kode voucher atau kartu voucher ini kepada petugas koperasi.\n4. Voucher berlaku sesuai tanggal masa aktif yang tercantum pada kartu."),
        ];

        // 6. Daftar opsi periode (6 bulan terakhir hingga bulan ini)
        $availablePeriods = [];
        for ($m = 0; $m < 6; $m++) {
            $p = $today->copy()->subMonths($m);
            $availablePeriods[] = [
                'period' => $p->format('Y-m'),
                'label'  => $p->locale('id')->isoFormat('MMMM Y'),
            ];
        }

        return Inertia::render('HabitVouchers/Index', [
            'selectedPeriod'     => $selectedPeriod,
            'periodName'         => $periodDate->locale('id')->isoFormat('MMMM Y'),
            'isCurrentMonth'     => $isCurrentMonth,
            'daysRemaining'      => $daysRemaining,
            'totalScore'         => $achievement['total_score'],
            'maxPossibleScore'   => $achievement['max_possible_score'],
            'achievedPercentage' => $achievement['achieved_percentage'],
            'targetPercentage'   => $achievement['target_percentage'],
            'targetScoreNeeded'  => $achievement['target_score_needed'],
            'pointsNeeded'       => $achievement['points_needed'],
            'percentageNeeded'   => $achievement['percentage_needed'],
            'isTargetReached'    => $achievement['is_target_reached'],
            'currentVoucher'     => $currentVoucher ? [
                'id'                  => $currentVoucher->id,
                'voucher_code'        => $currentVoucher->voucher_code,
                'title'               => $currentVoucher->title,
                'description'         => $currentVoucher->description,
                'reward_title'        => $currentVoucher->reward_title,
                'reward_value'        => $currentVoucher->reward_value,
                'target_percentage'   => $currentVoucher->target_percentage,
                'achieved_percentage' => $currentVoucher->achieved_percentage,
                'status'              => $currentVoucher->status,
                'is_redeemed'         => $currentVoucher->isRedeemed(),
                'claimed_at'          => $currentVoucher->claimed_at ? $currentVoucher->claimed_at->format('d M Y, H:i') : null,
                'redeemed_by_name'    => $currentVoucher->redeemer?->name,
                'generated_at'        => $currentVoucher->generated_at ? $currentVoucher->generated_at->format('d M Y, H:i') : null,
                'valid_until'         => $currentVoucher->valid_until ? $currentVoucher->valid_until->format('d M Y') : null,
                'is_expired'          => $currentVoucher->isExpired(),
            ] : null,
            'voucherHistory'     => $voucherHistory->map(fn($v) => [
                'id'                  => $v->id,
                'period'              => $v->period,
                'voucher_code'        => $v->voucher_code,
                'title'               => $v->title,
                'reward_title'        => $v->reward_title,
                'reward_value'        => $v->reward_value,
                'achieved_percentage' => $v->achieved_percentage,
                'target_percentage'   => $v->target_percentage,
                'status'              => $v->status,
                'is_redeemed'         => $v->isRedeemed(),
                'claimed_at'          => $v->claimed_at ? $v->claimed_at->format('d M Y, H:i') : null,
                'valid_until'         => $v->valid_until ? $v->valid_until->format('d M Y') : null,
            ]),
            'schoolSettings'     => $schoolSettings,
            'availablePeriods'   => $availablePeriods,
            'userCoopBalance'    => (float) $user->coop_balance,
            'userRfidUid'        => $user->rfid_uid,
        ]);
    }

    /**
     * Pegawai dapat mengklaim/mengonfirmasi voucher saat berada di koperasi sekolah.
     */
    public function claim(Request $request, HabitVoucher $voucher)
    {
        $user = Auth::user();

        // Pastikan voucher milik pegawai yang bersangkutan (atau admin)
        if ($voucher->user_id !== $user->id && !$user->hasRole('admin') && !$user->hasRole('superadmin')) {
            abort(403, 'Anda tidak berhak mengklaim voucher ini.');
        }

        if ($voucher->isRedeemed()) {
            return redirect()->back()->with('warning', 'Voucher ini sudah pernah ditukarkan di Koperasi Sekolah.');
        }

        if ($voucher->isExpired()) {
            return redirect()->back()->with('error', 'Masa berlaku voucher ini telah berakhir.');
        }

        $voucher->update([
            'status'           => 'claimed',
            'claimed_at'       => now(),
            'redemption_notes' => 'Ditukarkan di Koperasi Sekolah',
        ]);

        return redirect()->back()->with('success', 'Selamat! Voucher berhasil ditandai SUDAH DITUKARKAN di Koperasi Sekolah.');
    }

    /**
     * Pegawai mentransfer voucher menjadi Saldo Kartu RFID (Kartu Presensi Guru).
     */
    public function convertToRfid(Request $request, HabitVoucher $voucher)
    {
        $user = Auth::user();

        if ($voucher->user_id !== $user->id) {
            abort(403, 'Anda tidak berhak mentransfer voucher ini.');
        }

        if ($voucher->isRedeemed()) {
            return redirect()->back()->with('warning', 'Voucher ini sudah pernah ditukarkan / dikonversi.');
        }

        if ($voucher->isExpired()) {
            return redirect()->back()->with('error', 'Voucher telah kedaluwarsa.');
        }

        $success = $user->convertVoucherToBalance($voucher, $user);

        if (!$success) {
            return redirect()->back()->with('error', 'Gagal mentransfer voucher ke saldo kartu.');
        }

        $formattedAmount = 'Rp ' . number_format($voucher->getRewardNumericValue(), 0, ',', '.');
        return redirect()->back()->with('success', "Alhamdulillah! Voucher {$voucher->voucher_code} senilai {$formattedAmount} berhasil ditransfer menjadi Saldo Kartu RFID Presensi Anda. Saldo sekarang: Rp " . number_format($user->coop_balance, 0, ',', '.') . ".");
    }
}
