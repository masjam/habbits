<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherJournal extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'class_name',
        'subject',
        'theme',
        'meeting_number',
        'period',
        'time_start',
        'time_end',
        'material',
        'notes',
        'photo',
        'student_present',
        'student_sick',
        'student_leave',
        'student_absent',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
