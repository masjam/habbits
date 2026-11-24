<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoopStockLog extends Model
{
    protected $table = 'coop_stock_logs';

    protected $fillable = [
        'coop_product_id',
        'user_id',
        'type',
        'stock_before',
        'stock_after',
        'difference',
        'notes',
    ];

    protected $casts = [
        'stock_before' => 'integer',
        'stock_after'  => 'integer',
        'difference'   => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(CoopProduct::class, 'coop_product_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
