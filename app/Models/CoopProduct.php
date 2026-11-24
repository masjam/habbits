<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoopProduct extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'name',
        'barcode',
        'category',
        'price',
        'cost_price',
        'stock',
        'unit',
        'image_url',
        'is_active',
    ];

    protected $casts = [
        'price'      => 'float',
        'cost_price' => 'float',
        'stock'      => 'integer',
        'is_active'  => 'boolean',
    ];

    public function stockLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CoopStockLog::class, 'coop_product_id');
    }
}
