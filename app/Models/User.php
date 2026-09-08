<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;
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
        ];
    }

    public function habitLogs(): HasMany
    {
        return $this->hasMany(HabitLog::class);
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
