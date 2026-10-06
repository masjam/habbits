<?php

namespace App\Http\Controllers;

use App\Models\SuratKeluar;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicVerificationController extends Controller
{
    public function verifySuratKeluar($uuid)
    {
        if ($uuid === 'preview-only') {
            return Inertia::render('Public/VerifikasiSurat', [
                'status' => 'preview',
                'message' => 'Ini adalah pratinjau QR Code. Surat aslinya belum disimpan ke database.',
                'surat' => null
            ]);
        }

        $surat = SuratKeluar::where('uuid', $uuid)->first();

        if (!$surat) {
            return Inertia::render('Public/VerifikasiSurat', [
                'status' => 'not_found',
                'message' => 'Dokumen tidak ditemukan dalam sistem kami. Dokumen ini mungkin palsu.',
                'surat' => null
            ]);
        }

        return Inertia::render('Public/VerifikasiSurat', [
            'status' => 'valid',
            'message' => 'Dokumen ini VALID dan tercatat di sistem kami.',
            'surat' => [
                'nomor_surat' => $surat->nomor_surat,
                'tanggal_surat' => $surat->tanggal_surat,
                'tujuan' => $surat->tujuan,
                'perihal' => $surat->perihal,
                'hash' => $surat->document_hash,
                'dibuat_pada' => $surat->created_at->translatedFormat('l, d F Y H:i:s'),
            ]
        ]);
    }
}
