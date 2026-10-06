<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuratKeluar extends Model
{
    use HasFactory;

    protected $table = 'surat_keluar';

    protected $fillable = [
        'uuid',
        'document_hash',
        'nomor_surat',
        'tanggal_surat',
        'tujuan',
        'perihal',
        'builder_data',
        'file_path',
        'user_id',
    ];

    protected $casts = [
        'tanggal_surat' => 'date',
        'builder_data' => 'array',
    ];

    /**
     * User (Tata Usaha) yang menginput surat ini.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
