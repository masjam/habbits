<?php

namespace App\Http\Controllers\TataUsaha;

use App\Http\Controllers\Controller;
use App\Models\Disposisi;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Notifications\DisposisiNotification;

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
        $suratPerluDisposisi = SuratMasuk::where('perlu_disposisi', true)
                                         ->whereDoesntHave('disposisi')
                                         ->latest()
                                         ->get();
        // Hanya ambil user dengan role 'user' (pegawai biasa)
        $pegawai = User::whereHas('roles', function ($q) {
            $q->where('name', 'user');
        })->orderBy('name')->get(['id', 'name']);
        
        return Inertia::render('TataUsaha/Disposisi/Index', [
            'disposisi' => $disposisi,
            'suratMasuk' => $suratMasuk,
            'suratPerluDisposisi' => $suratPerluDisposisi,
            'pegawai' => $pegawai,
            'filters' => ['search' => $search]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'surat_masuk_id' => 'required|exists:surat_masuk,id',
            'penerima_id' => 'required|array|min:1',
            'penerima_id.*' => 'exists:users,id',
            'instruksi' => 'required|string',
            'batas_waktu' => 'nullable|date',
        ]);

        foreach ($validated['penerima_id'] as $p_id) {
            $disp = Disposisi::create([
                'surat_masuk_id' => $validated['surat_masuk_id'],
                'pemberi_id' => Auth::id(), // Kepala Sekolah / yang login
                'penerima_id' => $p_id,
                'instruksi' => $validated['instruksi'],
                'batas_waktu' => $validated['batas_waktu'] ?? null,
                'status' => 'menunggu',
            ]);
            
            // Notify penerima
            $penerima = User::find($p_id);
            if ($penerima) {
                $penerima->notify(new DisposisiNotification($disp));
            }
        }

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

    public function update(Request $request, Disposisi $disposisi)
    {
        $validated = $request->validate([
            'surat_masuk_id' => 'required|exists:surat_masuk,id',
            'penerima_id' => 'required',
            'instruksi' => 'required|string',
            'batas_waktu' => 'nullable|date',
        ]);
        
        $p_id = is_array($validated['penerima_id']) ? $validated['penerima_id'][0] : $validated['penerima_id'];

        $disposisi->update([
            'surat_masuk_id' => $validated['surat_masuk_id'],
            'penerima_id' => $p_id,
            'instruksi' => $validated['instruksi'],
            'batas_waktu' => $validated['batas_waktu'],
        ]);

        return redirect()->back()->with('success', 'Disposisi berhasil diperbarui.');
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

    public function print(Disposisi $disposisi)
    {
        // Pastikan hanya penerima, pemberi, atau Tata Usaha / KS yang bisa melihat
        $user = Auth::user();
        if ($disposisi->penerima_id !== $user->id && 
            $disposisi->pemberi_id !== $user->id && 
            !$user->hasAnyRole(['tata_usaha', 'kepala_sekolah', 'superadmin'])) {
            abort(403, 'Anda tidak berhak melihat disposisi ini.');
        }

        $disposisi->load(['suratMasuk', 'pemberi', 'penerima']);
        return view('print.disposisi', [
            'disposisi' => $disposisi,
            'surat' => $disposisi->suratMasuk
        ]);
    }
}
