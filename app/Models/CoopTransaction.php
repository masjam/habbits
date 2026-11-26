<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoopTransaction extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'transaction_code',
        'cashier_id',
        'user_id',
        'member_id',
        'voucher_id',
        'payment_method',
        'total_amount',
        'discount_amount',
        'voucher_amount',
        'rfid_paid_amount',
        'cash_paid_amount',
        'change_amount',
        'bon_amount',
        'bon_paid_amount',
        'bon_status',
        'items',
        'notes',
    ];

    protected $casts = [
        'total_amount'     => 'float',
        'discount_amount'  => 'float',
        'voucher_amount'   => 'float',
        'rfid_paid_amount' => 'float',
        'cash_paid_amount' => 'float',
        'change_amount'    => 'float',
        'bon_amount'       => 'float',
        'bon_paid_amount'  => 'float',
        'items'            => 'array',
    ];

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(CoopMember::class, 'member_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(HabitVoucher::class, 'voucher_id');
    }

    public function bonPayments(): HasMany
    {
        return $this->hasMany(CoopBonPayment::class, 'transaction_id');
    }
}
