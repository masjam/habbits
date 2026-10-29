<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    use HasUuid;
    protected $fillable = [
        'name',
        'description',
        'icon',
        'criteria_type',
        'criteria_value',
        'color_theme'
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_badges')->withPivot('unlocked_at')->withTimestamps();
    }
}
