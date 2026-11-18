<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class TeacherJournal extends Model
{
    use HasUuid;
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

    /**
     * Konten uraian materi ditampilkan dengan v-html di halaman admin, jadi
     * selalu disanitasi (HTMLPurifier) setiap kali ditulis.
     */
    public function setMaterialAttribute($value): void
    {
        $this->attributes['material'] = $value === null
            ? null
            : \Stevebauman\Purify\Facades\Purify::clean((string) $value);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
