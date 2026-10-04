<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    protected $fillable = [
        'user_id',
        'document_type',
        'title',
        'document_number',
        'document_year',
        'file_path',
        'file_extension',
        'is_verified',
        'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
