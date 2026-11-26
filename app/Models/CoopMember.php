<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoopMember extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'member_code',
        'name',
        'type',
        'phone',
        'rfid_uid',
        'coop_balance',
        'bon_limit',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'coop_balance' => 'float',
        'bon_limit'    => 'float',
        'is_active'    => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(CoopTransaction::class, 'member_id');
    }

    public function balanceLogs(): HasMany
    {
        return $this->hasMany(CoopBalanceLog::class, 'member_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'wali_murid' => 'Wali Murid',
            'siswa'      => 'Siswa',
            default      => 'Umum',
        };
    }

    /**
     * Tambah saldo kartu/deposit member dan catat mutasi.
     */
    public function addCoopBalance(
        float $amount,
        string $type,
        ?string $referenceId = null,
        ?string $description = null,
        ?User $operator = null
    ): CoopBalanceLog {
        $before = (float) $this->coop_balance;
        $after = $before + $amount;

        $this->update(['coop_balance' => $after]);

        return CoopBalanceLog::create([
            'member_id'      => $this->id,
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
     * Potong saldo kartu/deposit member dan catat mutasi.
     */
    public function deductCoopBalance(
        float $amount,
        string $type,
        ?string $referenceId = null,
        ?string $description = null,
        ?User $operator = null
    ): CoopBalanceLog {
        $before = (float) $this->coop_balance;
        $after = max(0, $before - $amount);

        $this->update(['coop_balance' => $after]);

        return CoopBalanceLog::create([
            'member_id'      => $this->id,
            'type'           => $type,
            'amount'         => -$amount,
            'balance_before' => $before,
            'balance_after'  => $after,
            'reference_id'   => $referenceId,
            'description'    => $description,
            'operator_id'    => $operator?->id,
        ]);
    }
}
