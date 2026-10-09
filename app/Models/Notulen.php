<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notulen extends Model
{
    protected $guarded = [];

    protected $casts = [
        'tanggal_waktu' => 'datetime',
        'dokumentasi' => 'array',
        'is_approved' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
