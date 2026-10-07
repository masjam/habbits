<?php

namespace App\Http\Controllers\TataUsaha;

use App\Http\Controllers\Controller;
use App\Models\SuratMasuk;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class SuratMasukController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        
        $query = SuratMasuk::with('uploader')->latest();
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nomor_surat', 'like', "%{$search}%")
                  ->orWhere('pengirim', 'like', "%{$search}%")
                  ->orWhere('perihal', 'like', "%{$search}%");
            });
        }
        
        $suratMasuk = $query->paginate(10)->withQueryString();
        
        return Inertia::render('TataUsaha/SuratMasuk/Index', [
            'suratMasuk' => $suratMasuk,
            'filters' => ['search' => $search]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nomor_surat' => 'required|string|unique:surat_masuk,nomor_surat',
            'tanggal_surat' => 'required|date',
            'tanggal_diterima' => 'required|date',
            'pengirim' => 'required|string',
            'perihal' => 'required|string',
            'jenis_surat' => 'nullable|string',
            'perlu_disposisi' => 'boolean',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', // Max 5MB
        ]);

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('surat_masuk', 'public');
        }

        SuratMasuk::create([
            'nomor_surat' => $validated['nomor_surat'],
            'tanggal_surat' => $validated['tanggal_surat'],
            'tanggal_diterima' => $validated['tanggal_diterima'],
            'pengirim' => $validated['pengirim'],
            'perihal' => $validated['perihal'],
            'jenis_surat' => $validated['jenis_surat'] ?? null,
            'perlu_disposisi' => $validated['perlu_disposisi'] ?? false,
            'file_path' => $filePath,
            'user_id' => Auth::id(),
            'status' => 'baru',
        ]);

        return redirect()->back()->with('success', 'Surat Masuk berhasil ditambahkan.');
    }

    public function destroy(SuratMasuk $suratMasuk)
    {
        if ($suratMasuk->file_path) {
            Storage::disk('public')->delete($suratMasuk->file_path);
        }
        $suratMasuk->delete();
        
        return redirect()->back()->with('success', 'Surat Masuk berhasil dihapus.');
    }

    public function update(Request $request, SuratMasuk $suratMasuk)
    {
        $validated = $request->validate([
            'nomor_surat' => 'required|string|unique:surat_masuk,nomor_surat,' . $suratMasuk->id,
            'tanggal_surat' => 'required|date',
            'tanggal_diterima' => 'required|date',
            'pengirim' => 'required|string',
            'perihal' => 'required|string',
            'jenis_surat' => 'nullable|string',
            'perlu_disposisi' => 'boolean',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($request->hasFile('file')) {
            if ($suratMasuk->file_path) {
                Storage::disk('public')->delete($suratMasuk->file_path);
            }
            $validated['file_path'] = $request->file('file')->store('surat_masuk', 'public');
        }

        $suratMasuk->update([
            'nomor_surat' => $validated['nomor_surat'],
            'tanggal_surat' => $validated['tanggal_surat'],
            'tanggal_diterima' => $validated['tanggal_diterima'],
            'pengirim' => $validated['pengirim'],
            'perihal' => $validated['perihal'],
            'jenis_surat' => $validated['jenis_surat'] ?? null,
            'perlu_disposisi' => $validated['perlu_disposisi'] ?? false,
            'file_path' => $validated['file_path'] ?? $suratMasuk->file_path,
        ]);

        return redirect()->back()->with('success', 'Surat Masuk berhasil diperbarui.');
    }
}
