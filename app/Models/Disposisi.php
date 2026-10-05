<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Disposisi extends Model
{
    use HasFactory;

    protected $table = 'disposisi';

    protected $fillable = [
        'surat_masuk_id',
        'pemberi_id',
        'penerima_id',
        'instruksi',
        'batas_waktu',
        'status',
        'catatan_penyelesaian',
    ];

    protected $casts = [
        'batas_waktu' => 'date',
    ];

    /**
     * Surat masuk terkait disposisi ini.
     */
    public function suratMasuk(): BelongsTo
    {
        return $this->belongsTo(SuratMasuk::class, 'surat_masuk_id');
    }

    /**
     * User yang memberikan disposisi (Kepala Sekolah/Pimpinan).
     */
    public function pemberi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pemberi_id');
    }

    /**
     * User yang menerima disposisi (Pegawai yang ditugaskan).
     */
    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penerima_id');
    }
}
