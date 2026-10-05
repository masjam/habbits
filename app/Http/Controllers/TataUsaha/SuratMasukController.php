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
}
