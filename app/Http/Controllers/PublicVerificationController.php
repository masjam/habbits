<?php

namespace App\Http\Controllers;

use App\Models\SuratKeluar;
use App\Models\Notulen;
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
                'file_path' => $surat->file_path,
                'dibuat_pada' => $surat->created_at->translatedFormat('l, d F Y H:i:s'),
            ]
        ]);
    }
    public function verifyNotulen($uuid)
    {
        $notulen = Notulen::where('uuid', $uuid)->first();

        if (!$notulen) {
            return Inertia::render('Public/VerifikasiNotulen', [
                'status' => 'not_found',
                'message' => 'Dokumen Notulen tidak ditemukan. Dokumen ini mungkin palsu.',
                'notulen' => null
            ]);
        }

        return Inertia::render('Public/VerifikasiNotulen', [
            'status' => 'valid',
            'message' => 'Dokumen Notulen ini VALID dan tercatat di sistem kami.',
            'notulen' => [
                'judul_rapat' => $notulen->judul_rapat,
                'jenis_rapat' => $notulen->jenis_rapat,
                'tanggal_waktu' => $notulen->tanggal_waktu->translatedFormat('l, d F Y H:i'),
                'lokasi' => $notulen->lokasi,
                'pimpinan_rapat' => $notulen->pimpinan_rapat,
                'is_approved' => (bool)$notulen->is_approved,
                'dibuat_pada' => $notulen->created_at->translatedFormat('l, d F Y H:i:s'),
            ]
        ]);
    }
}
