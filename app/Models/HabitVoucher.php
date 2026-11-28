<?php

namespace App\Models;

use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HabitVoucher extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'voucher_code',
        'period',
        'title',
        'description',
        'reward_title',
        'reward_value',
        'target_percentage',
        'achieved_percentage',
        'total_score',
        'max_possible_score',
        'status',
        'claimed_at',
        'valid_until',
        'qr_payload',
        'generated_by',
        'generated_at',
        'redeemed_by',
        'redemption_notes',
        'transaction_code',
        'transaction_amount',
        'discount_amount',
        'paid_amount',
        'change_amount',
        'items_summary',
    ];

    protected $casts = [
        'claimed_at'          => 'datetime',
        'generated_at'        => 'datetime',
        'valid_until'         => 'date',
        'target_percentage'   => 'float',
        'achieved_percentage' => 'float',
        'total_score'         => 'integer',
        'max_possible_score'  => 'integer',
        'transaction_amount'  => 'float',
        'discount_amount'     => 'float',
        'paid_amount'         => 'float',
        'change_amount'       => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function redeemer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }

    public function logs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HabitVoucherLog::class, 'habit_voucher_id')->orderByDesc('created_at')->orderByDesc('id');
    }

    public function isRedeemed(): bool
    {
        return $this->status === 'claimed' || !empty($this->claimed_at);
    }

    public function isClaimed(): bool
    {
        return $this->isRedeemed();
    }

    public function isExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        if ($this->valid_until && Carbon::today()->gt($this->valid_until) && !$this->isRedeemed()) {
            return true;
        }

        return false;
    }

    public function isUnredeemed(): bool
    {
        return !$this->isRedeemed() && !$this->isExpired();
    }

    /**
     * Ambil estimasi nominal reward voucher dalam bentuk angka (float).
     * Contoh: "Voucher Belanja Koperasi Sekolah Rp 50.000" -> 50000.
     */
    public function getRewardNumericValue(): float
    {
        if (!empty($this->discount_amount) && $this->discount_amount > 0) {
            return (float) $this->discount_amount;
        }

        $text = $this->reward_value ?: '';
        if (preg_match('/(?:rp\.?|idr)\s*([\d\.,]+)/i', $text, $matches)) {
            $clean = preg_replace('/[^\d]/', '', $matches[1]);
            if (is_numeric($clean) && (float) $clean > 0) {
                return (float) $clean;
            }
        }

        return 50000.0; // Default nominal voucher koperasi
    }

    /**
     * Konversikan voucher ini ke Saldo Kartu RFID / ATM Presensi pegawai.
     */
    public function convertToRfidBalance(?User $operator = null): bool
    {
        if (!$this->user) {
            $this->load('user');
        }

        return $this->user ? $this->user->convertVoucherToBalance($this, $operator) : false;
    }

    /**
     * Ambil target habit efektif untuk pegawai berdasarkan pengaturan yang dibuat oleh Admin.
     */
    public static function getEffectiveTargetForUser(User $user): float
    {
        // 1. Target global dari pengaturan admin (Setting 'monthly_target_score', default 80.0)
        $settingTarget = Setting::get('monthly_target_score');
        $target = $settingTarget !== null ? (float) $settingTarget : 80.0;

        // 2. Jika user tidak aktif dan admin menentukan target khusus inaktif
        if ($user->status_kehadiran !== 'Aktif' && !is_null($user->target_tidak_aktif)) {
            return (float) $user->target_tidak_aktif;
        }

        // 3. Jika pegawai memiliki target personal yang disetujui
        if (!is_null($user->personal_target)) {
            return (float) $user->personal_target;
        }

        // 4. Jika divisi pegawai memiliki target divisi yang diatur admin
        if (!empty($user->divisi)) {
            $divisionTargets = Division::getTargetsMap();
            if (isset($divisionTargets[$user->divisi])) {
                return (float) $divisionTargets[$user->divisi];
            }
        }

        return $target;
    }

    /**
     * Hitung pencapaian habit pegawai untuk suatu periode tanpa menerbitkan voucher.
     */
    public static function calculateUserAchievement(User $user, ?string $period = null): array
    {
        if (!$period) {
            $period = Carbon::today()->format('Y-m');
        }

        try {
            $date = Carbon::parse($period . '-01');
        } catch (\Exception $e) {
            $date = Carbon::today()->startOfMonth();
            $period = $date->format('Y-m');
        }

        $startOfMonth = $date->copy()->startOfMonth()->format('Y-m-d');
        $endOfMonth   = $date->copy()->endOfMonth()->format('Y-m-d');
        $daysInMonth  = $date->daysInMonth;

        // Skor maksimal harian habit aktif reguler (bukan pengganti haid)
        $dailyMax = Habit::allActiveCached()
            ->where('is_pengganti_haid', false)
            ->sum('skor_maksimal') ?: 100;
        $maxPossibleScore = (int) ($dailyMax * $daysInMonth);

        // Skor habit yang diperoleh pegawai pada periode ini
        $totalScore = (int) HabitLog::where('user_id', $user->id)
            ->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
            ->sum('skor_diperoleh');

        $achievedPercentage = $maxPossibleScore > 0 ? round(($totalScore / $maxPossibleScore) * 100, 1) : 0.0;

        // Target pencapaian membaca pengaturan admin
        $targetPercentage = self::getEffectiveTargetForUser($user);

        $targetScoreNeeded = (int) ceil(($targetPercentage / 100) * $maxPossibleScore);
        $pointsNeeded = max(0, $targetScoreNeeded - $totalScore);
        $percentageNeeded = max(0.0, round($targetPercentage - $achievedPercentage, 1));
        $isTargetReached = ($achievedPercentage >= $targetPercentage) && ($totalScore > 0);

        return [
            'period'              => $period,
            'period_date'         => $date,
            'total_score'         => $totalScore,
            'max_possible_score'  => $maxPossibleScore,
            'achieved_percentage' => $achievedPercentage,
            'target_percentage'   => $targetPercentage,
            'target_score_needed' => $targetScoreNeeded,
            'points_needed'       => $pointsNeeded,
            'percentage_needed'   => $percentageNeeded,
            'is_target_reached'   => $isTargetReached,
        ];
    }

    /**
     * Diterbitkan secara resmi oleh Admin untuk pegawai yang memenuhi target habit.
     */
    public static function generateForUser(User $user, string $period, ?User $admin = null): ?self
    {
        $stats = self::calculateUserAchievement($user, $period);

        if (!$stats['is_target_reached']) {
            return null; // Tidak memenuhi target habit sekolah
        }

        $date = $stats['period_date'];
        $monthName = $date->locale('id')->isoFormat('MMMM Y');

        $rewardTitle = Setting::get('voucher_reward_title', 'Voucher Apresiasi Target Habit');
        $rewardValue = Setting::get('voucher_reward_value', 'Voucher Belanja Koperasi Sekolah Rp 50.000');
        $rewardDesc = Setting::get('voucher_reward_description', "Diberikan kepada {$user->name} atas pencapaian target habit sekolah periode {$monthName} ({$stats['achieved_percentage']}% / Target {$stats['target_percentage']}%). Dapat ditukarkan langsung ke bagian Koperasi Sekolah.");

        $validityDays = (int) Setting::get('voucher_validity_days', 60);
        $validUntil = $date->copy()->addDays($validityDays)->endOfMonth();

        $voucher = self::where('user_id', $user->id)
            ->where('period', $period)
            ->first();

        if (!$voucher) {
            $randomSuffix = strtoupper(bin2hex(random_bytes(4)));
            $voucherCode = 'SDAM-VCR-' . str_replace('-', '', $period) . '-' . $randomSuffix;

            $qrPayload = json_encode([
                'app'         => 'HABIT_SDAM',
                'type'        => 'KOPERASI_VOUCHER',
                'code'        => $voucherCode,
                'user_id'     => $user->id,
                'user_name'   => $user->name,
                'nip'         => $user->nip ?: '-',
                'period'      => $period,
                'achieved'    => $stats['achieved_percentage'] . '%',
                'target'      => $stats['target_percentage'] . '%',
                'redeem_at'   => 'Koperasi Sekolah SD Muhammadiyah 1 Krian',
                'valid_until' => $validUntil->format('Y-m-d'),
            ]);

            $voucher = self::create([
                'user_id'             => $user->id,
                'voucher_code'        => $voucherCode,
                'period'              => $period,
                'title'               => "Voucher Apresiasi Habit {$monthName}",
                'description'         => $rewardDesc,
                'reward_title'        => $rewardTitle,
                'reward_value'        => $rewardValue,
                'target_percentage'   => $stats['target_percentage'],
                'achieved_percentage' => $stats['achieved_percentage'],
                'total_score'         => $stats['total_score'],
                'max_possible_score'  => $stats['max_possible_score'],
                'status'              => 'active',
                'valid_until'         => $validUntil->format('Y-m-d'),
                'qr_payload'          => $qrPayload,
                'generated_by'        => $admin?->id,
                'generated_at'        => now(),
            ]);
        } else {
            // Update jika skor bertambah
            $voucher->update([
                'achieved_percentage' => $stats['achieved_percentage'],
                'total_score'         => $stats['total_score'],
                'generated_by'        => $admin?->id ?? $voucher->generated_by,
                'generated_at'        => $voucher->generated_at ?? now(),
            ]);
        }

        return $voucher;
    }
}
