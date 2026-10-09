<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenstruationLog extends Model
{
    protected $fillable = [
        'user_id',
        'waktu_mulai',
        'waktu_selesai',
    ];

    protected $casts = [
        'waktu_mulai'    => 'encrypted:datetime',
        'waktu_selesai'  => 'encrypted:datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
