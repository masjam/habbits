<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeacherAdministration extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'user_id',
        'academic_year',
        'semester',
        'grade_level',
        'subject',
        'category',
        'title',
        'description',
        'file_path',
        'file_name',
        'file_extension',
        'file_size',
        'status',
        'feedback',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'file_size' => 'integer',
    ];

    protected $appends = [
        'category_label',
        'formatted_file_size',
    ];

    public const CATEGORIES = [
        'modul_ajar'  => 'Modul Ajar / RPP',
        'prota'       => 'Program Tahunan (Prota)',
        'promes'      => 'Program Semester (Promes)',
        'atp_cp'      => 'Alur Tujuan Pembelajaran (ATP) / CP',
        'kktp'        => 'Kriteria Ketuntasan (KKTP / KKM)',
        'kisi_soal'   => 'Kisi-kisi & Naskah Soal',
        'buku_nilai'  => 'Format / Buku Nilai',
        'kalender'    => 'Kalender Pendidikan & Rincian Pekan Efektif',
        'jurnal_lain' => 'Jurnal / Agenda Mengajar',
        'lainnya'     => 'Berkas Administrasi Lainnya',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst(str_replace('_', ' ', $this->category));
    }

    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($this->file_size, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
