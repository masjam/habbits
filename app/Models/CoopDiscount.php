<?php

namespace App\Models;

use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoopDiscount extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'name',
        'discount_type',
        'discount_value',
        'max_discount',
        'min_purchase',
        'member_type',
        'product_category',
        'start_date',
        'end_date',
        'is_active',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'discount_value' => 'float',
        'max_discount'   => 'float',
        'min_purchase'   => 'float',
        'start_date'     => 'date:Y-m-d',
        'end_date'       => 'date:Y-m-d',
        'is_active'      => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActiveOn(Builder $query, ?Carbon $date = null): Builder
    {
        $day = ($date ?? Carbon::today())->toDateString();

        return $query->where('is_active', true)
            ->where(function (Builder $q) use ($day) {
                $q->whereNull('start_date')->orWhereDate('start_date', '<=', $day);
            })
            ->where(function (Builder $q) use ($day) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $day);
            });
    }

    public function isEligibleForCustomer(?string $customerType, ?string $memberSubType = null): bool
    {
        if (!$this->member_type) {
            return true;
        }

        if ($this->member_type === 'registered') {
            return in_array($customerType, ['user', 'member'], true);
        }

        if ($this->member_type === 'pegawai') {
            return $customerType === 'user';
        }

        return $customerType === 'member' && $memberSubType === $this->member_type;
    }

    /**
     * Hitung nominal diskon server-side berdasarkan item keranjang, tipe pelanggan, dan kategori produk.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int|string, string|null>  $productCategoryMap
     */
    public function calculateDiscount(
        array $items,
        ?string $customerType,
        ?string $memberSubType,
        array $productCategoryMap
    ): float {
        if (!$this->isEligibleForCustomer($customerType, $memberSubType)) {
            return 0.0;
        }

        $totalGross = (float) collect($items)->sum(fn ($item) => (float) ($item['subtotal'] ?? 0));
        if ($totalGross <= 0) {
            return 0.0;
        }

        $eligibleSubtotal = 0.0;
        foreach ($items as $item) {
            $subtotal = (float) ($item['subtotal'] ?? 0);
            if ($subtotal <= 0) {
                continue;
            }

            if (!$this->product_category) {
                $eligibleSubtotal += $subtotal;
                continue;
            }

            $prodId = $item['id'] ?? null;
            $itemCat = $prodId !== null ? ($productCategoryMap[$prodId] ?? ($item['category'] ?? null)) : ($item['category'] ?? null);
            if ($itemCat === $this->product_category) {
                $eligibleSubtotal += $subtotal;
            }
        }

        if ($eligibleSubtotal <= 0 || $eligibleSubtotal < (float) $this->min_purchase) {
            return 0.0;
        }

        if ($this->discount_type === 'percentage') {
            $pct = min(100.0, max(0.0, (float) $this->discount_value));
            $discount = round($eligibleSubtotal * ($pct / 100.0), 0);
            if ($this->max_discount !== null && (float) $this->max_discount > 0) {
                $discount = min($discount, (float) $this->max_discount);
            }
        } else {
            $discount = min($eligibleSubtotal, max(0.0, (float) $this->discount_value));
        }

        return min($totalGross, max(0.0, (float) $discount));
    }

    public function getMemberTypeLabelAttribute(): string
    {
        return match ($this->member_type) {
            'pegawai'    => 'Pegawai / Guru',
            'wali_murid' => 'Wali Murid',
            'siswa'      => 'Siswa',
            'umum'       => 'Member Umum',
            'registered' => 'Semua Member & Pegawai',
            default      => 'Semua Pelanggan',
        };
    }
}
