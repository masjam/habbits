<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasPushSubscriptions, HasUuid;
    use HasRoles {
        hasRole as spatieHasRole;
        getRoleNames as spatieGetRoleNames;
    }

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'gender',
        'current_streak',
        'longest_streak',
        'personal_target',
        'avatar',
        'nip',
        'divisi',
        'evaluation_category',
        'status_kehadiran',
        'catatan_pimpinan',
        'target_tidak_aktif',
        'can_multi_login',
        'work_start',
        'work_end',
        'late_tolerance',
        'rfid_uid',
        'coop_balance',
        'coop_bon_limit',
        'fingerprint_id',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
        'catatan_pimpinan',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'must_change_password' => 'boolean',
            'can_multi_login'   => 'boolean',
            'late_tolerance'    => 'integer',
            'coop_balance'      => 'float',
            'coop_bon_limit'    => 'float',
        ];
    }

    public function habitLogs(): HasMany
    {
        return $this->hasMany(HabitLog::class);
    }

    public function habitVouchers(): HasMany
    {
        return $this->hasMany(HabitVoucher::class);
    }

    public function coopTransactions(): HasMany
    {
        return $this->hasMany(CoopTransaction::class, 'user_id');
    }

    public function teacherJournals(): HasMany
    {
        return $this->hasMany(TeacherJournal::class, 'user_id');
    }

    public function teacherAdministrations(): HasMany
    {
        return $this->hasMany(TeacherAdministration::class, 'user_id');
    }

    /**
     * Tentukan kategori evaluasi KPI pegawai ('guru' atau 'karyawan').
     * Sumber kebenaran tunggal untuk deteksi otomatis kategori.
     */
    public function evaluationCategory(): string
    {
        if (in_array($this->evaluation_category, ['guru', 'karyawan'], true)) {
            return $this->evaluation_category;
        }

        $divisi = strtolower(trim((string) $this->divisi));

        $teacherKeywords = ['guru', 'wali kelas', 'pengajar', 'tahfidz', 'kurikulum', 'mapel', 'kelas'];
        foreach ($teacherKeywords as $kw) {
            if ($divisi !== '' && str_contains($divisi, $kw)) {
                return 'guru';
            }
        }

        $staffKeywords = ['tata usaha', 'tu', 'koperasi', 'keuangan', 'sarpras', 'kebersihan', 'keamanan', 'satpam', 'karyawan', 'tendik', 'staf', 'staff', 'it', 'humas', 'perpustakaan'];
        foreach ($staffKeywords as $kw) {
            if ($divisi !== '' && str_contains($divisi, $kw)) {
                return 'karyawan';
            }
        }

        if ($this->hasRole('kurikulum')) {
            return 'guru';
        }

        if ($this->hasAnyRole(['tata_usaha', 'koperasi'])) {
            return 'karyawan';
        }

        // Jika pernah mengisi jurnal guru atau perangkat pembelajaran, perlakukan sebagai guru
        if ($this->relationLoaded('teacherJournals') ? $this->teacherJournals->isNotEmpty() : $this->teacherJournals()->exists()) {
            return 'guru';
        }

        if ($this->relationLoaded('teacherAdministrations') ? $this->teacherAdministrations->isNotEmpty() : $this->teacherAdministrations()->exists()) {
            return 'guru';
        }

        return 'karyawan';
    }

    public function coopBalanceLogs(): HasMany
    {
        return $this->hasMany(CoopBalanceLog::class, 'user_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function performanceEvaluations(): HasMany
    {
        return $this->hasMany(PerformanceEvaluation::class, 'user_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::saved(function () {
            self::clearUserRoleCache();
        });

        static::deleted(function () {
            self::clearUserRoleCache();
        });
    }

    public static function clearUserRoleCache(): void
    {
        \Illuminate\Support\Facades\Cache::forget('non_admin_user_ids');
    }

    /**
     * Scope query untuk menyembunyikan akun role admin & superadmin.
     */
    public function scopeNonAdmin($query)
    {
        return $query->whereDoesntHave('roles', fn($q) => $q->whereIn('name', ['admin', 'superadmin']));
    }

    /**
     * Dapatkan daftar id pengguna non-admin (pegawai) ter-cache singkat.
     *
     * @return array<int>
     */
    public static function getNonAdminUserIds(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('non_admin_user_ids', 120, function () {
            return static::nonAdmin()
                ->pluck('id')
                ->toArray();
        });
    }

    public function clearNotificationCache(): void
    {
        \Illuminate\Support\Facades\Cache::forget("user_{$this->id}_notifications_unread_count");
        \Illuminate\Support\Facades\Cache::forget("user_{$this->id}_notifications_latest");
    }

    public function getCachedUnreadNotificationsCount(): int
    {
        return (int) \Illuminate\Support\Facades\Cache::remember(
            "user_{$this->id}_notifications_unread_count",
            60,
            fn () => $this->unreadNotifications()->count()
        );
    }

    public function getCachedLatestNotifications()
    {
        return \Illuminate\Support\Facades\Cache::remember(
            "user_{$this->id}_notifications_latest",
            60,
            fn () => $this->notifications()->take(5)->get()
        );
    }

    /**
     * Relasi ke jadwal piket yang ditugaskan ke user ini.
     */
    public function dutySchedules(): BelongsToMany
    {
        return $this->belongsToMany(DutySchedule::class, 'duty_schedule_user')->withTimestamps();
    }

    /**
     * Dapatkan jadwal kerja efektif (memprioritaskan jadwal piket di hari terkait, lalu perorangan, lalu harian sekolah, lalu jadwal global).
     */
    public function getEffectiveWorkSchedule(?string $date = null): array
    {
        $targetDate = $date ? Carbon::parse($date) : Carbon::today();
        $dayOfWeek = (int) $targetDate->dayOfWeekIso; // 1 = Senin, ..., 7 = Minggu

        // 1. Cek apakah user sedang bertugas Piket aktif di hari ini
        if ($this->relationLoaded('dutySchedules')) {
            $piket = $this->dutySchedules
                ->first(fn ($s) => (int)$s->day_of_week === $dayOfWeek && $s->is_active);
        } else {
            $piket = $this->dutySchedules()
                ->where('day_of_week', $dayOfWeek)
                ->where('is_active', true)
                ->first();
        }

        $globalTolSetting = Setting::get('presensi_late_tolerance');
        $globalTol = ($globalTolSetting !== null && $globalTolSetting !== '') ? (int)$globalTolSetting : 15;

        // 0. Cek apakah ada jadwal khusus (Special Schedule)
        $targetDateStr = $targetDate->format('Y-m-d');
        $specialSchedule = SpecialSchedule::getActiveCached()
            ->first(function ($s) use ($targetDateStr) {
                $start = is_string($s->start_date) ? substr($s->start_date, 0, 10) : $s->start_date->format('Y-m-d');
                $end = is_string($s->end_date) ? substr($s->end_date, 0, 10) : $s->end_date->format('Y-m-d');
                return $start <= $targetDateStr && $end >= $targetDateStr;
            });

        if ($specialSchedule) {
            $usePiketOverride = $piket && ($specialSchedule->piket_time_in || $specialSchedule->piket_time_out);

            return [
                'work_start'     => $usePiketOverride ? ($specialSchedule->piket_time_in ?: $specialSchedule->time_in) : $specialSchedule->time_in,
                'work_end'       => $usePiketOverride ? ($specialSchedule->piket_time_out ?: $specialSchedule->time_out) : $specialSchedule->time_out,
                'late_tolerance' => $specialSchedule->late_tolerance !== null ? (int)$specialSchedule->late_tolerance : $globalTol,
                'is_custom'      => true,
                'is_piket'       => (bool) $usePiketOverride,
                'piket_name'     => $usePiketOverride ? $piket->name : null,
                'piket_id'       => $usePiketOverride ? $piket->id : null,
                'is_special'     => true,
                'special_name'   => $specialSchedule->name,
                'is_off_day'     => false,
                'latitude'       => $specialSchedule->latitude,
                'longitude'      => $specialSchedule->longitude,
                'radius'         => $specialSchedule->radius,
            ];
        }

        if ($piket) {
            return [
                'work_start'     => $piket->time_in,
                'work_end'       => $piket->time_out,
                'late_tolerance' => $piket->late_tolerance !== null ? (int)$piket->late_tolerance : $globalTol,
                'is_custom'      => true,
                'is_piket'       => true,
                'piket_name'     => $piket->name,
                'piket_id'       => $piket->id,
                'is_off_day'     => false,
            ];
        }

        // 2. Cek apakah ada jadwal khusus perorangan
        $hasUserCustom = $this->work_start !== null || $this->work_end !== null || $this->late_tolerance !== null;

        // 3. Cek pengaturan jadwal harian sekolah (presensi_daily_schedules JSON)
        $dailySchedulesJson = Setting::get('presensi_daily_schedules');
        $daySchedule = null;
        if ($dailySchedulesJson) {
            $decoded = json_decode($dailySchedulesJson, true);
            if (isset($decoded[$dayOfWeek])) {
                $daySchedule = $decoded[$dayOfWeek];
            }
        }

        // 4. Default global fallback
        $globalStart = Setting::get('presensi_work_start') ?: '07:00';
        $globalEnd   = Setting::get('presensi_work_end') ?: '15:00';

        $defaultDayStart = ($daySchedule && !empty($daySchedule['start'])) ? $daySchedule['start'] : $globalStart;
        $defaultDayEnd   = ($daySchedule && !empty($daySchedule['end'])) ? $daySchedule['end'] : $globalEnd;
        $defaultDayTol   = ($daySchedule && isset($daySchedule['tolerance'])) ? (int)$daySchedule['tolerance'] : $globalTol;
        $isOffDay        = ($daySchedule && isset($daySchedule['is_active']) && !$daySchedule['is_active']);

        return [
            'work_start'     => $this->work_start ?: $defaultDayStart,
            'work_end'       => $this->work_end ?: $defaultDayEnd,
            'late_tolerance' => $this->late_tolerance !== null ? (int)$this->late_tolerance : $defaultDayTol,
            'is_custom'      => $hasUserCustom,
            'is_piket'       => false,
            'is_off_day'     => $isOffDay,
        ];
    }

    /**
     * Cek apakah akun ini aslinya adalah Superadmin di database.
     */
    public function isActualSuperadmin(): bool
    {
        $this->loadMissing('roles');
        return $this->roles->contains('name', 'superadmin');
    }

    public function isKoperasi(): bool
    {
        return $this->hasRole('koperasi');
    }

    public function canAccessKoperasiPos(): bool
    {
        return $this->hasAnyRole(['koperasi', 'admin', 'superadmin']) || $this->isActualSuperadmin();
    }

    /**
     * Tambah saldo kartu RFID Koperasi dan catat log mutasi.
     */
    public function addCoopBalance(float $amount, string $type, ?string $referenceId = null, ?string $description = null, ?User $operator = null): CoopBalanceLog
    {
        $amount = abs($amount);
        $before = (float) $this->coop_balance;
        $after  = $before + $amount;

        $this->update(['coop_balance' => $after]);

        return CoopBalanceLog::create([
            'user_id'        => $this->id,
            'type'           => $type,
            'amount'         => $amount,
            'balance_before' => $before,
            'balance_after'  => $after,
            'reference_id'   => $referenceId,
            'description'    => $description,
            'operator_id'    => $operator?->id,
        ]);
    }

    /**
     * Kurangi saldo kartu RFID Koperasi dan catat log mutasi.
     */
    public function deductCoopBalance(float $amount, string $type, ?string $referenceId = null, ?string $description = null, ?User $operator = null): CoopBalanceLog
    {
        $amount = abs($amount);
        $before = (float) $this->coop_balance;
        $after  = max(0.0, $before - $amount);

        $this->update(['coop_balance' => $after]);

        return CoopBalanceLog::create([
            'user_id'        => $this->id,
            'type'           => $type,
            'amount'         => -$amount,
            'balance_before' => $before,
            'balance_after'  => $after,
            'reference_id'   => $referenceId,
            'description'    => $description,
            'operator_id'    => $operator?->id,
        ]);
    }

    /**
     * Konversi voucher habit menjadi Saldo Kartu RFID (Kartu Presensi Guru).
     */
    public function convertVoucherToBalance(HabitVoucher $voucher, ?User $operator = null): bool
    {
        if ($voucher->user_id !== $this->id || $voucher->isRedeemed() || $voucher->isExpired()) {
            return false;
        }

        $numericValue = $voucher->getRewardNumericValue();

        $voucher->update([
            'status'           => 'claimed',
            'claimed_at'       => now(),
            'redeemed_by'      => $operator?->id ?? $this->id,
            'redemption_notes' => 'Dikonversi ke Saldo Kartu RFID Presensi Guru',
            'discount_amount'  => $numericValue,
            'transaction_code' => 'TOPUP-VCR-' . $voucher->voucher_code,
        ]);

        $this->addCoopBalance(
            $numericValue,
            'voucher_conversion',
            $voucher->voucher_code,
            "Konversi {$voucher->title} ({$voucher->reward_value}) ke Saldo Kartu RFID",
            $operator
        );

        return true;
    }

    /**
     * Dapatkan role simulasi saat mode maintenance aktif untuk superadmin.
     */
    public function getSimulatedRole(): ?string
    {
        if (session()->has('maintenance_simulated_role') && $this->isActualSuperadmin()) {
            return session('maintenance_simulated_role');
        }
        return null;
    }

    /**
     * Override hasRole untuk mendukung simulasi role saat mode maintenance.
     */
    public function hasRole($roles, ?string $guard = null): bool
    {
        $simulated = $this->getSimulatedRole();
        if ($simulated) {
            if ($simulated === 'superadmin') {
                return $this->spatieHasRole($roles, $guard);
            }

            $flattened = collect($roles)
                ->flatten()
                ->flatMap(function ($item) {
                    if (is_string($item) && str_contains($item, '|')) {
                        return explode('|', $item);
                    }
                    if ($item instanceof \Spatie\Permission\Models\Role || is_object($item)) {
                        return [$item->name ?? ''];
                    }
                    return [(string) $item];
                });

            return $flattened->contains($simulated);
        }

        return $this->spatieHasRole($roles, $guard);
    }

    /**
     * Override hasAnyRole agar memanggil hasRole dengan array yang diflatten.
     */
    public function hasAnyRole(...$roles): bool
    {
        return $this->hasRole($roles);
    }

    /**
     * Override getRoleNames untuk mendukung simulasi role saat mode maintenance.
     */
    public function getRoleNames(): \Illuminate\Support\Collection
    {
        $simulated = $this->getSimulatedRole();
        if ($simulated) {
            return collect([$simulated]);
        }

        return $this->spatieGetRoleNames();
    }

    public function menstruationLogs(): HasMany
    {
        return $this->hasMany(MenstruationLog::class);
    }

    public function badges()
    {
        return $this->belongsToMany(Badge::class, 'user_badges')
                    ->withPivot('unlocked_at')
                    ->withTimestamps();
    }

    public function suratMasuk(): HasMany
    {
        return $this->hasMany(SuratMasuk::class, 'user_id');
    }

    public function suratKeluar(): HasMany
    {
        return $this->hasMany(SuratKeluar::class, 'user_id');
    }

    public function disposisiDiberikan(): HasMany
    {
        return $this->hasMany(Disposisi::class, 'pemberi_id');
    }

    public function disposisiDiterima(): HasMany
    {
        return $this->hasMany(Disposisi::class, 'penerima_id');
    }

    public function chatConversations(): BelongsToMany
    {
        return $this->belongsToMany(ChatConversation::class, 'chat_conversation_user', 'user_id', 'conversation_id')
            ->withPivot(['last_read_at', 'is_muted'])
            ->withTimestamps();
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'user_id');
    }
}


