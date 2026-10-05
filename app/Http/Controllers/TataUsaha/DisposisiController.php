<?php

namespace App\Http\Controllers\TataUsaha;

use App\Http\Controllers\Controller;
use App\Models\Disposisi;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class DisposisiController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        
        $query = Disposisi::with(['suratMasuk', 'pemberi', 'penerima'])->latest();
        
        if ($search) {
            $query->whereHas('suratMasuk', function($q) use ($search) {
                $q->where('nomor_surat', 'like', "%{$search}%")
                  ->orWhere('perihal', 'like', "%{$search}%");
            })->orWhereHas('penerima', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }
        
        $disposisi = $query->paginate(10)->withQueryString();
        $suratMasuk = SuratMasuk::where('status', '!=', 'selesai')->get();
        // Get all users except superadmin maybe, or just everyone
        $pegawai = User::orderBy('name')->get(['id', 'name']);
        
        return Inertia::render('TataUsaha/Disposisi/Index', [
            'disposisi' => $disposisi,
            'suratMasuk' => $suratMasuk,
            'pegawai' => $pegawai,
            'filters' => ['search' => $search]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'surat_masuk_id' => 'required|exists:surat_masuk,id',
            'penerima_id' => 'required|exists:users,id',
            'instruksi' => 'required|string',
            'batas_waktu' => 'nullable|date',
        ]);

        Disposisi::create([
            'surat_masuk_id' => $validated['surat_masuk_id'],
            'pemberi_id' => Auth::id(), // Kepala Sekolah / yang login
            'penerima_id' => $validated['penerima_id'],
            'instruksi' => $validated['instruksi'],
            'batas_waktu' => $validated['batas_waktu'] ?? null,
            'status' => 'menunggu',
        ]);

        // Update status surat masuk
        $surat = SuratMasuk::find($validated['surat_masuk_id']);
        if ($surat->status === 'baru') {
            $surat->update(['status' => 'didisposisikan']);
        }

        return redirect()->back()->with('success', 'Disposisi berhasil dikirim.');
    }

    public function destroy(Disposisi $disposisi)
    {
        $disposisi->delete();
        return redirect()->back()->with('success', 'Disposisi berhasil dihapus.');
    }

    public function updateStatus(Request $request, Disposisi $disposisi)
    {
        $validated = $request->validate([
            'status' => 'required|in:menunggu,dibaca,dikerjakan,selesai',
            'catatan_penyelesaian' => 'nullable|string'
        ]);

        $disposisi->update($validated);

        return redirect()->back()->with('success', 'Status disposisi diperbarui.');
    }
}
