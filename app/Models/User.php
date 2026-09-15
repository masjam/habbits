<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
    use HasFactory, Notifiable, SoftDeletes, HasPushSubscriptions;
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
        'status_kehadiran',
        'catatan_pimpinan',
        'target_tidak_aktif',
        'can_multi_login',
        'work_start',
        'work_end',
        'late_tolerance',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
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
            'can_multi_login'   => 'boolean',
            'late_tolerance'    => 'integer',
        ];
    }

    public function habitLogs(): HasMany
    {
        return $this->hasMany(HabitLog::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
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
        $piket = $this->dutySchedules()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        $globalTol = (int)(Setting::where('key', 'presensi_late_tolerance')->value('value') ?: 15);

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
        $dailySchedulesJson = Setting::where('key', 'presensi_daily_schedules')->value('value');
        $daySchedule = null;
        if ($dailySchedulesJson) {
            $decoded = json_decode($dailySchedulesJson, true);
            if (isset($decoded[$dayOfWeek])) {
                $daySchedule = $decoded[$dayOfWeek];
            }
        }

        // 4. Default global fallback
        $globalStart = Setting::where('key', 'presensi_work_start')->value('value') ?: '07:00';
        $globalEnd   = Setting::where('key', 'presensi_work_end')->value('value') ?: '15:00';

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
}


