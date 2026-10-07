<?php

namespace App\Http\Controllers\TataUsaha;

use App\Http\Controllers\Controller;
use App\Models\SuratKeluar;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

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
            'tanggal_hijriah' => '11 Rabi\'ul Akhir 1447 H', // TODO: Implement Hijri Converter if needed
            'users' => User::whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', ['admin', 'superadmin']);
            })->orderBy('name')->get(['id', 'name'])
        ]);
    }

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'jenis_surat' => 'required|string|in:Umum,Tugas,Keterangan',
            'nomor_surat' => 'required|string',
            'lampiran' => 'nullable|string',
            'perihal' => 'nullable|string',
            'kepada' => 'nullable|string',
            'di' => 'nullable|string',
            'tanggal_masehi' => 'required|string',
            'tanggal_hijriah' => 'required|string',
            'isi_surat' => 'required|string',
            'pegawai_ditugaskan' => 'nullable|array',
            'pokok_tugas' => 'nullable|string',
        ]);

        if ($validated['jenis_surat'] === 'Tugas') {
            $validated['perihal'] = 'Surat Tugas';
            $validated['kepada'] = 'Yang Bersangkutan';
            $validated['di'] = 'Tempat';
            $validated['lampiran'] = '-';
        } elseif ($validated['jenis_surat'] === 'Keterangan') {
            $validated['perihal'] = 'Surat Keterangan';
            $validated['kepada'] = 'Yang Berkepentingan';
            $validated['di'] = 'Tempat';
            $validated['lampiran'] = '-';
        }

        $settings = \App\Models\Setting::whereIn('key', ['kepsek_nama', 'kepsek_nbm'])->pluck('value', 'key');
        $validated['nama_kepsek'] = $settings['kepsek_nama'] ?? 'Joko Kiswanto, S.Pd.I, M.Pd';
        $validated['nbm_kepsek'] = $settings['kepsek_nbm'] ?? '1.032.440';

        $arabic = new \ArPHP\I18N\Arabic();
        $validated['bismillah'] = $arabic->utf8Glyphs('بِسْمِ اللَّهِ الرَّحْمَنِ الرَّحِيم', 150, false, false);
        $validated['salam_pembuka'] = $arabic->utf8Glyphs('السَّلاَمُ عَلَيْكُمْ وَرَحْمَةُ اللهِ وَبَرَكَاتُهُ', 150, false, false);
        $validated['salam_penutup'] = $arabic->utf8Glyphs('وَالسَّلاَمُ عَلَيْكُمْ وَرَحْمَةُ اللهِ وَبَرَكَاتُهُ', 150, false, false);

        // Dummy QR Code for preview
        $verifyUrl = url('/verifikasi-surat/preview-only');
        $qrCode = base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(100)->margin(0)->generate($verifyUrl));
        $validated['qrCode'] = $qrCode;

        $validated['is_draft'] = true; // Preview is always draft

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.surat-keluar', $validated);
        $pdf->setPaper('A4', 'portrait');
        
        return $pdf->stream('preview_surat.pdf');
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'jenis_surat' => 'required|string|in:Umum,Tugas,Keterangan',
            'nomor_surat' => 'required|string',
            'lampiran' => 'nullable|string',
            'perihal' => 'nullable|string',
            'kepada' => 'nullable|string',
            'di' => 'nullable|string',
            'tanggal_masehi' => 'required|string',
            'tanggal_hijriah' => 'required|string',
            'isi_surat' => 'required|string',
            'pegawai_ditugaskan' => 'nullable|array',
            'pokok_tugas' => 'nullable|string',
        ]);

        if ($validated['jenis_surat'] === 'Tugas') {
            $validated['perihal'] = 'Surat Tugas';
            $validated['kepada'] = 'Yang Bersangkutan';
            $validated['di'] = 'Tempat';
            $validated['lampiran'] = '-';
        } elseif ($validated['jenis_surat'] === 'Keterangan') {
            $validated['perihal'] = 'Surat Keterangan';
            $validated['kepada'] = 'Yang Berkepentingan';
            $validated['di'] = 'Tempat';
            $validated['lampiran'] = '-';
        }

        $settings = \App\Models\Setting::whereIn('key', ['kepsek_nama', 'kepsek_nbm'])->pluck('value', 'key');
        $validated['nama_kepsek'] = $settings['kepsek_nama'] ?? 'Joko Kiswanto, S.Pd.I, M.Pd';
        $validated['nbm_kepsek'] = $settings['kepsek_nbm'] ?? '1.032.440';

        $arabic = new \ArPHP\I18N\Arabic();
        $validated['bismillah'] = $arabic->utf8Glyphs('بِسْمِ اللَّهِ الرَّحْمَنِ الرَّحِيم', 150, false, false);
        $validated['salam_pembuka'] = $arabic->utf8Glyphs('السَّلاَمُ عَلَيْكُمْ وَرَحْمَةُ اللهِ وَبَرَكَاتُهُ', 150, false, false);
        $validated['salam_penutup'] = $arabic->utf8Glyphs('وَالسَّلاَمُ عَلَيْكُمْ وَرَحْمَةُ اللهِ وَبَرَكَاتُهُ', 150, false, false);

        // Scenario A: Hashing data and creating QR
        $uuid = (string) \Illuminate\Support\Str::uuid();
        $dataToHash = $validated['nomor_surat'] . $validated['perihal'] . $validated['isi_surat'];
        $hash = hash('sha256', $dataToHash);
        
        $verifyUrl = url('/verifikasi-surat/' . $uuid);
        $qrCode = base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(100)->margin(0)->generate($verifyUrl));
        $validated['qrCode'] = $qrCode;

        $validated['is_draft'] = true; // Newly generated is draft

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.surat-keluar', $validated);
        
        // Atur ukuran kertas
        $pdf->setPaper('A4', 'portrait');
        
        $filename = 'surat_keluar_' . time() . '.pdf';
        $path = 'surat_keluar/' . $filename;
        
        Storage::disk('public')->put($path, $pdf->output());

        // Parse date from masehi to Y-m-d format for DB loosely
        $date = date('Y-m-d'); 
        
        $db_tujuan = $validated['kepada'];
        $db_perihal = $validated['perihal'];
        
        if ($validated['jenis_surat'] === 'Tugas') {
            $db_tujuan = !empty($validated['pegawai_ditugaskan']) ? implode(', ', (array)$validated['pegawai_ditugaskan']) : 'Yang Bersangkutan';
            $db_perihal = !empty($validated['pokok_tugas']) ? $validated['pokok_tugas'] : 'Surat Tugas';
        }

        SuratKeluar::create([
            'uuid' => $uuid,
            'document_hash' => $hash,
            'nomor_surat' => $validated['nomor_surat'],
            'tanggal_surat' => $date,
            'tujuan' => $db_tujuan,
            'perihal' => $db_perihal,
            'builder_data' => $validated, // Save all builder fields
            'file_path' => $path,
            'status' => 'draft',
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('tata-usaha.surat-keluar.index')
            ->with('success', 'Surat keluar otomatis berhasil dibuat dan disimpan.');
    }

    public function editBuilder(SuratKeluar $suratKeluar)
    {
        if (!$suratKeluar->builder_data) {
            return redirect()->back()->with('error', 'Surat ini tidak dibuat melalui sistem otomatis sehingga tidak bisa diedit ulang.');
        }

        return Inertia::render('TataUsaha/SuratKeluar/Builder', [
            'isEdit' => true,
            'suratId' => $suratKeluar->id,
            'builderData' => $suratKeluar->builder_data,
            // Pass these just in case the builder requires them, though builderData already has them
            'next_nomor' => $suratKeluar->nomor_surat, 
            'tanggal_masehi' => $suratKeluar->builder_data['tanggal_masehi'] ?? \Carbon\Carbon::now()->translatedFormat('d F Y') . ' M',
            'tanggal_hijriah' => $suratKeluar->builder_data['tanggal_hijriah'] ?? '11 Rabi\'ul Akhir 1447 H',
            'users' => User::whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', ['admin', 'superadmin']);
            })->orderBy('name')->get(['id', 'name'])
        ]);
    }

    public function updateBuilder(Request $request, SuratKeluar $suratKeluar)
    {
        $validated = $request->validate([
            'jenis_surat' => 'required|string|in:Umum,Tugas,Keterangan',
            'nomor_surat' => 'required|string',
            'lampiran' => 'nullable|string',
            'perihal' => 'nullable|string',
            'kepada' => 'nullable|string',
            'di' => 'nullable|string',
            'tanggal_masehi' => 'required|string',
            'tanggal_hijriah' => 'required|string',
            'isi_surat' => 'required|string',
            'pegawai_ditugaskan' => 'nullable|array',
            'pokok_tugas' => 'nullable|string',
        ]);

        if ($validated['jenis_surat'] === 'Tugas') {
            $validated['perihal'] = 'Surat Tugas';
            $validated['kepada'] = 'Yang Bersangkutan';
            $validated['di'] = 'Tempat';
            $validated['lampiran'] = '-';
        } elseif ($validated['jenis_surat'] === 'Keterangan') {
            $validated['perihal'] = 'Surat Keterangan';
            $validated['kepada'] = 'Yang Berkepentingan';
            $validated['di'] = 'Tempat';
            $validated['lampiran'] = '-';
        }

        $settings = \App\Models\Setting::whereIn('key', ['kepsek_nama', 'kepsek_nbm'])->pluck('value', 'key');
        $validated['nama_kepsek'] = $settings['kepsek_nama'] ?? 'Joko Kiswanto, S.Pd.I, M.Pd';
        $validated['nbm_kepsek'] = $settings['kepsek_nbm'] ?? '1.032.440';

        $arabic = new \ArPHP\I18N\Arabic();
        $validated['bismillah'] = $arabic->utf8Glyphs('بِسْمِ اللَّهِ الرَّحْمَنِ الرَّحِيم', 150, false, false);
        $validated['salam_pembuka'] = $arabic->utf8Glyphs('السَّلاَمُ عَلَيْكُمْ وَرَحْمَةُ اللهِ وَبَرَكَاتُهُ', 150, false, false);
        $validated['salam_penutup'] = $arabic->utf8Glyphs('وَالسَّلاَمُ عَلَيْكُمْ وَرَحْمَةُ اللهِ وَبَرَكَاتُهُ', 150, false, false);

        // Keep the old UUID so the old printed QR code might still work if we want? 
        // Wait, if the document changes, the hash MUST change. 
        // We regenerate the hash. We can keep the same UUID.
        $uuid = $suratKeluar->uuid ?? (string) \Illuminate\Support\Str::uuid();
        $dataToHash = $validated['nomor_surat'] . $validated['perihal'] . $validated['isi_surat'];
        $hash = hash('sha256', $dataToHash);
        
        $verifyUrl = url('/verifikasi-surat/' . $uuid);
        $qrCode = base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(100)->margin(0)->generate($verifyUrl));
        $validated['qrCode'] = $qrCode;

        $validated['is_draft'] = true;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.surat-keluar', $validated);
        $pdf->setPaper('A4', 'portrait');
        
        // Remove old file
        if ($suratKeluar->file_path) {
            Storage::disk('public')->delete($suratKeluar->file_path);
        }

        $filename = 'surat_keluar_' . time() . '.pdf';
        $path = 'surat_keluar/' . $filename;
        Storage::disk('public')->put($path, $pdf->output());

        $db_tujuan = $validated['kepada'];
        $db_perihal = $validated['perihal'];
        
        if ($validated['jenis_surat'] === 'Tugas') {
            $db_tujuan = !empty($validated['pegawai_ditugaskan']) ? implode(', ', (array)$validated['pegawai_ditugaskan']) : 'Yang Bersangkutan';
            $db_perihal = !empty($validated['pokok_tugas']) ? $validated['pokok_tugas'] : 'Surat Tugas';
        }

        $suratKeluar->update([
            'uuid' => $uuid,
            'document_hash' => $hash,
            'nomor_surat' => $validated['nomor_surat'],
            'tujuan' => $db_tujuan,
            'perihal' => $db_perihal,
            'builder_data' => $validated,
            'file_path' => $path,
            'status' => 'draft',
        ]);

        return redirect()->route('tata-usaha.surat-keluar.index')
            ->with('success', 'Surat keluar berhasil diperbarui dan digenerate ulang.');
    }

    public function approve(SuratKeluar $suratKeluar)
    {
        if ($suratKeluar->status === 'approved') {
            return redirect()->back()->with('error', 'Surat ini sudah di-approve.');
        }

        $suratKeluar->update(['status' => 'approved']);

        if ($suratKeluar->builder_data) {
            // Regenerate PDF without Draft watermark
            $validated = $suratKeluar->builder_data;
            $validated['is_draft'] = false;
            $validated['qrCode'] = base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(100)->margin(0)->generate(url('/verifikasi-surat/' . $suratKeluar->uuid)));

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.surat-keluar', $validated);
            $pdf->setPaper('A4', 'portrait');

            // Save replacing the old file
            Storage::disk('public')->put($suratKeluar->file_path, $pdf->output());
        }

        return redirect()->back()->with('success', 'Surat Keluar berhasil di-approve.');
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
