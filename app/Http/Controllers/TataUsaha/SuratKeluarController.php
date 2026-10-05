<?php

namespace App\Http\Controllers\TataUsaha;

use App\Http\Controllers\Controller;
use App\Models\SuratKeluar;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class SuratKeluarController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        
        $query = SuratKeluar::with('uploader')->latest();
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nomor_surat', 'like', "%{$search}%")
                  ->orWhere('tujuan', 'like', "%{$search}%")
                  ->orWhere('perihal', 'like', "%{$search}%");
            });
        }
        
        $suratKeluar = $query->paginate(10)->withQueryString();
        
        return Inertia::render('TataUsaha/SuratKeluar/Index', [
            'suratKeluar' => $suratKeluar,
            'filters' => ['search' => $search]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nomor_surat' => 'required|string|unique:surat_keluar,nomor_surat',
            'tanggal_surat' => 'required|date',
            'tujuan' => 'required|string',
            'perihal' => 'required|string',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120', // Max 5MB
        ]);

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('surat_keluar', 'public');
        }

        SuratKeluar::create([
            'nomor_surat' => $validated['nomor_surat'],
            'tanggal_surat' => $validated['tanggal_surat'],
            'tujuan' => $validated['tujuan'],
            'perihal' => $validated['perihal'],
            'file_path' => $filePath,
            'user_id' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Surat Keluar berhasil ditambahkan.');
    }

    public function builder()
    {
        // Cari nomor terakhir untuk generate format berikutnya
        $lastSurat = SuratKeluar::latest('id')->first();
        $nextNum = 1;
        if ($lastSurat && preg_match('/^(\d+)\//', $lastSurat->nomor_surat, $matches)) {
            $nextNum = (int)$matches[1] + 1;
        }
        $year = date('Y');
        // Example logic for roman numeral month
        $months = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        $monthRoman = $months[date('n')];
        
        $nextNomor = "{$nextNum}/ III.4.AU/F/{$year}"; // Customize as needed

        return Inertia::render('TataUsaha/SuratKeluar/Builder', [
            'next_nomor' => $nextNomor,
            'tanggal_masehi' => \Carbon\Carbon::now()->translatedFormat('d F Y') . ' M',
            'tanggal_hijriah' => '11 Rabi\'ul Akhir 1447 H' // TODO: Implement Hijri Converter if needed
        ]);
    }

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'nomor_surat' => 'required|string',
            'lampiran' => 'nullable|string',
            'perihal' => 'required|string',
            'kepada' => 'required|string',
            'di' => 'required|string',
            'tanggal_masehi' => 'required|string',
            'tanggal_hijriah' => 'required|string',
            'isi_surat' => 'required|string',
        ]);

        $settings = \App\Models\Setting::whereIn('key', ['kepsek_nama', 'kepsek_nbm'])->pluck('value', 'key');
        $validated['nama_kepsek'] = $settings['kepsek_nama'] ?? 'Joko Kiswanto, S.Pd.I, M.Pd';
        $validated['nbm_kepsek'] = $settings['kepsek_nbm'] ?? '1.032.440';

        $arabic = new \ArPHP\I18N\Arabic();
        $validated['bismillah'] = $arabic->utf8Glyphs('بِسْمِ اللَّهِ الرَّحْمَنِ الرَّحِيم', 150, false, false);
        $validated['salam_pembuka'] = $arabic->utf8Glyphs('السَّلاَمُ عَلَيْكُمْ وَرَحْمَةُ اللهِ وَبَرَكَاتُهُ', 150, false, false);
        $validated['salam_penutup'] = $arabic->utf8Glyphs('وَالسَّلاَمُ عَلَيْكُمْ وَرَحْمَةُ اللهِ وَبَرَكَاتُهُ', 150, false, false);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.surat-keluar', $validated);
        $pdf->setPaper('A4', 'portrait');
        
        return $pdf->stream('preview_surat.pdf');
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'nomor_surat' => 'required|string',
            'lampiran' => 'nullable|string',
            'perihal' => 'required|string',
            'kepada' => 'required|string',
            'di' => 'required|string',
            'tanggal_masehi' => 'required|string',
            'tanggal_hijriah' => 'required|string',
            'isi_surat' => 'required|string',
        ]);

        $settings = \App\Models\Setting::whereIn('key', ['kepsek_nama', 'kepsek_nbm'])->pluck('value', 'key');
        $validated['nama_kepsek'] = $settings['kepsek_nama'] ?? 'Joko Kiswanto, S.Pd.I, M.Pd';
        $validated['nbm_kepsek'] = $settings['kepsek_nbm'] ?? '1.032.440';

        $arabic = new \ArPHP\I18N\Arabic();
        $validated['bismillah'] = $arabic->utf8Glyphs('بِسْمِ اللَّهِ الرَّحْمَنِ الرَّحِيم', 150, false, false);
        $validated['salam_pembuka'] = $arabic->utf8Glyphs('السَّلاَمُ عَلَيْكُمْ وَرَحْمَةُ اللهِ وَبَرَكَاتُهُ', 150, false, false);
        $validated['salam_penutup'] = $arabic->utf8Glyphs('وَالسَّلاَمُ عَلَيْكُمْ وَرَحْمَةُ اللهِ وَبَرَكَاتُهُ', 150, false, false);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.surat-keluar', $validated);
        
        // Atur ukuran kertas
        $pdf->setPaper('A4', 'portrait');
        
        $filename = 'surat_keluar_' . time() . '.pdf';
        $path = 'surat_keluar/' . $filename;
        
        Storage::disk('public')->put($path, $pdf->output());

        // Parse date from masehi to Y-m-d format for DB loosely
        $date = date('Y-m-d'); 
        
        SuratKeluar::create([
            'nomor_surat' => $validated['nomor_surat'],
            'tanggal_surat' => $date,
            'tujuan' => $validated['kepada'],
            'perihal' => $validated['perihal'],
            'file_path' => $path,
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('tata-usaha.surat-keluar.index')
            ->with('success', 'Surat keluar otomatis berhasil dibuat dan disimpan.');
    }

    public function destroy(SuratKeluar $suratKeluar)
    {
        if ($suratKeluar->file_path) {
            Storage::disk('public')->delete($suratKeluar->file_path);
        }
        $suratKeluar->delete();
        
        return redirect()->back()->with('success', 'Surat Keluar berhasil dihapus.');
    }
}
