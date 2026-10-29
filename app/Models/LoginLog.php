<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'location',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
