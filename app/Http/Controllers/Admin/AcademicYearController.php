<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeacherAdministration;
use App\Services\AcademicYearService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class AcademicYearController extends Controller
{
    public function __construct(
        protected AcademicYearService $academicYearService
    ) {}

    /**
     * Tambahkan tahun ajaran baru ke sistem.
     */
    public function store(Request $request)
    {
        $request->validate([
            'year' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/'],
        ], [
            'year.required' => 'Tahun ajaran wajib diisi.',
            'year.regex'    => 'Format tahun ajaran harus YYYY/YYYY (contoh: 2026/2027).',
        ]);

        try {
            $this->academicYearService->addYear($request->year);
            return redirect()->back()->with('success', "Tahun ajaran {$request->year} berhasil ditambahkan ke daftar.");
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['year' => $e->getMessage()]);
        }
    }

    /**
     * Tetapkan tahun ajaran & semester aktif.
     */
    public function setActive(Request $request)
    {
        $request->validate([
            'year'     => 'required|string',
            'semester' => 'required|in:ganjil,genap',
        ]);

        try {
            $this->academicYearService->setActivePeriod($request->year, $request->semester);
            $semesterLabel = ucfirst($request->semester);
            return redirect()->back()->with('success', "Tahun ajaran aktif diubah menjadi {$request->year} (Semester {$semesterLabel}).");
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['year' => $e->getMessage()]);
        }
    }

    /**
     * Ubah status pengarsipan tahun ajaran (arsipkan / buka kunci arsip).
     */
    public function toggleArchive(Request $request)
    {
        $request->validate([
            'year'        => 'required|string',
            'is_archived' => 'required|boolean',
        ]);

        try {
            $this->academicYearService->setArchived($request->year, (bool) $request->is_archived);
            $actionLabel = $request->is_archived ? 'diarsipkan' : 'diaktifkan kembali';
            return redirect()->back()->with('success', "Tahun ajaran {$request->year} berhasil {$actionLabel}.");
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Hapus tahun ajaran dari daftar.
     */
    public function destroy($year)
    {
        $decodedYear = urldecode($year);

        // Cek apakah ada berkas di tahun ajaran ini
        $docCount = TeacherAdministration::where('academic_year', $decodedYear)->count();
        if ($docCount > 0) {
            return redirect()->back()->withErrors([
                'error' => "Tahun ajaran {$decodedYear} memiliki {$docCount} dokumen administrasi guru dan tidak dapat dihapus. Silakan gunakan fitur arsip sebagai gantinya."
            ]);
        }

        try {
            $this->academicYearService->removeYear($decodedYear);
            return redirect()->back()->with('success', "Tahun ajaran {$decodedYear} berhasil dihapus dari daftar.");
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Unduh paket arsip ZIP seluruh dokumen administrasi guru per tahun ajaran.
     */
    public function exportArchive($year)
    {
        $decodedYear = urldecode($year);
        $documents = TeacherAdministration::where('academic_year', $decodedYear)
            ->with('user:id,name,nip')
            ->get();

        if ($documents->isEmpty()) {
            return redirect()->back()->withErrors([
                'error' => "Belum ada berkas administrasi guru yang diunggah pada tahun ajaran {$decodedYear}."
            ]);
        }

        $safeYear = Str::slug($decodedYear);
        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipFileName = "Arsip_Administrasi_Guru_{$safeYear}_" . time() . ".zip";
        $zipPath = $tempDir . DIRECTORY_SEPARATOR . $zipFileName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return redirect()->back()->withErrors(['error' => 'Gagal membuat file arsip ZIP di server.']);
        }

        $disk = Storage::disk('administrasi_guru');
        $addedFilesCount = 0;

        foreach ($documents as $doc) {
            if ($doc->file_path && $disk->exists($doc->file_path)) {
                $fileContent = $disk->get($doc->file_path);
                
                // Struktur folder arsip: Semester_{Ganjil/Genap}/{Kategori}/{Nama_Guru}_{Judul}.{ext}
                $semesterFolder = 'Semester_' . ucfirst($doc->semester);
                $categoryFolder = Str::slug($doc->category);
                $teacherName = $doc->user ? Str::slug($doc->user->name) : 'guru-anonim';
                $cleanTitle = Str::slug($doc->title);
                $ext = $doc->file_extension ?: pathinfo($doc->file_name, PATHINFO_EXTENSION);
                
                $archiveRelativePath = sprintf(
                    '%s/%s/%s_%s.%s',
                    $semesterFolder,
                    $categoryFolder,
                    $teacherName,
                    $cleanTitle,
                    $ext
                );

                $zip->addFromString($archiveRelativePath, $fileContent);
                $addedFilesCount++;
            }
        }

        $zip->close();

        if ($addedFilesCount === 0) {
            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }
            return redirect()->back()->withErrors([
                'error' => "Berkas fisik untuk tahun ajaran {$decodedYear} tidak ditemukan di sistem penyimpanan."
            ]);
        }

        $downloadName = "Arsip_Administrasi_Guru_{$safeYear}.zip";
        return response()->download($zipPath, $downloadName)->deleteFileAfterSend(true);
    }
}
