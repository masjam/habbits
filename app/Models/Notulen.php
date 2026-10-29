<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Notulen extends Model
{
    use HasUuid;
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

    public function pesertas()
    {
        return $this->belongsToMany(User::class, 'notulen_user')->withPivot('is_hadir')->withTimestamps();
    }

    public function getDaftarHadirAttribute($value)
    {
        $guests = $value ? explode(', ', $value) : [];
        $registered = $this->pesertas()->wherePivot('is_hadir', true)->pluck('name')->toArray();
        return implode(', ', array_filter(array_merge($guests, $registered)));
    }

    public function getPesertaRapatAttribute($value)
    {
        $guests = $value ? explode(', ', $value) : [];
        $registered = $this->pesertas()->pluck('name')->toArray();
        return implode(', ', array_filter(array_merge($guests, $registered)));
    }

    public function getTtdNotulisAttribute($value)
    {
        if (!$value) return null;
        if (str_starts_with($value, 'data:image') || str_starts_with($value, 'http')) {
            return $value;
        }
        return asset('storage/' . $value);
    }

    public function getTtdPimpinanAttribute($value)
    {
        if (!$value) return null;
        if (str_starts_with($value, 'data:image') || str_starts_with($value, 'http')) {
            return $value;
        }
        return asset('storage/' . $value);
    }
}
