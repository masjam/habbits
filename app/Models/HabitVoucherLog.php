<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HabitVoucherLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'habit_voucher_id',
        'actor_id',
        'action',
        'notes',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(HabitVoucher::class, 'habit_voucher_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
