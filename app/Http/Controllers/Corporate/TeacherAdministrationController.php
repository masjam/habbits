<?php

namespace App\Http\Controllers\Corporate;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\TeacherAdministration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TeacherAdministrationController extends Controller
{
    /**
     * Hitung tahun ajaran & semester saat ini berdasarkan tanggal kalender.
     */
    private function getCurrentAcademicPeriod(): array
    {
        $now = now();
        $year = $now->year;
        $month = $now->month;

        if ($month >= 7) {
            $academicYear = $year . '/' . ($year + 1);
            $semester = 'ganjil';
        } else {
            $academicYear = ($year - 1) . '/' . $year;
            $semester = 'genap';
        }

        return [$academicYear, $semester];
    }

    /**
     * Tampilkan halaman daftar berkas administrasi guru (sisi Guru).
     */
    public function index(Request $request)
    {
        $featureEnabled = Setting::where('key', 'feature_administrasi_guru')->value('value') ?? '1';
        if ($featureEnabled === '0' || $featureEnabled === 'false') {
            abort(403, 'Fitur Administrasi Guru saat ini sedang dinonaktifkan oleh administrator.');
        }

        $user = Auth::user();
        [$currentAcademicYear, $currentSemester] = $this->getCurrentAcademicPeriod();

        $selectedYear = $request->input('academic_year', $currentAcademicYear);
        $selectedSemester = $request->input('semester', $currentSemester);
        $selectedCategory = $request->input('category');
        $selectedStatus = $request->input('status');

        $query = TeacherAdministration::query()
            ->where('user_id', $user->id)
            ->with(['reviewer:id,name'])
            ->latest();

        if ($selectedYear && $selectedYear !== 'all') {
            $query->where('academic_year', $selectedYear);
        }

        if ($selectedSemester && $selectedSemester !== 'all') {
            $query->where('semester', $selectedSemester);
        }

        if ($selectedCategory && $selectedCategory !== 'all') {
            $query->where('category', $selectedCategory);
        }

        if ($selectedStatus && $selectedStatus !== 'all') {
            $query->where('status', $selectedStatus);
        }

        $documents = $query->paginate(15)->withQueryString();

        // Statistik Progres Dokumen untuk Periode Terpilih
        $periodDocs = TeacherAdministration::where('user_id', $user->id)
            ->when($selectedYear && $selectedYear !== 'all', fn($q) => $q->where('academic_year', $selectedYear))
            ->when($selectedSemester && $selectedSemester !== 'all', fn($q) => $q->where('semester', $selectedSemester))
            ->get();

        $stats = [
            'total'      => $periodDocs->count(),
            'approved'   => $periodDocs->where('status', 'disetujui')->count(),
            'pending'    => $periodDocs->where('status', 'menunggu_verifikasi')->count(),
            'revision'   => $periodDocs->where('status', 'perlu_revisi')->count(),
        ];

        // Daftar kategori wajib untuk panduan kelengkapan
        $mandatoryCategories = [
            'modul_ajar',
            'prota',
            'promes',
            'atp_cp',
            'kktp',
            'kisi_soal',
            'buku_nilai',
        ];

        $uploadedCategories = $periodDocs->pluck('category')->unique()->toArray();
        $checklistProgress = [
            'total_mandatory' => count($mandatoryCategories),
            'completed_count' => count(array_intersect($mandatoryCategories, $uploadedCategories)),
        ];

        // Opsi pilihan tahun ajaran
        $yearsList = [
            '2024/2025',
            '2025/2026',
            '2026/2027',
            '2027/2028',
        ];
        if (!in_array($currentAcademicYear, $yearsList)) {
            $yearsList[] = $currentAcademicYear;
        }

        return Inertia::render('TeacherAdministration/Index', [
            'documents'           => $documents,
            'filters'             => [
                'academic_year' => $selectedYear,
                'semester'      => $selectedSemester,
                'category'      => $selectedCategory,
                'status'        => $selectedStatus,
            ],
            'categories'          => TeacherAdministration::CATEGORIES,
            'yearsList'           => $yearsList,
            'currentAcademicYear' => $currentAcademicYear,
            'currentSemester'     => $currentSemester,
            'stats'               => $stats,
            'checklistProgress'   => $checklistProgress,
        ]);
    }

    /**
     * Simpan berkas administrasi guru baru ke storage.
     */
    public function store(Request $request)
    {
        $featureEnabled = Setting::where('key', 'feature_administrasi_guru')->value('value') ?? '1';
        if ($featureEnabled === '0' || $featureEnabled === 'false') {
            abort(403, 'Fitur Administrasi Guru dinonaktifkan.');
        }

        $validated = $request->validate([
            'academic_year' => 'required|string|max:20',
            'semester'      => 'required|in:ganjil,genap',
            'grade_level'   => 'nullable|string|max:50',
            'subject'       => 'nullable|string|max:100',
            'category'      => 'required|string|max:50',
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string|max:1000',
            'file'          => 'required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar|max:5120', // Maks. 5 MB
        ], [
            'file.max'   => 'Ukuran berkas melebihi batas maksimal 5 MB.',
            'file.mimes' => 'Format berkas harus berupa PDF, Word, Excel, PowerPoint, atau ZIP/RAR.',
        ]);

        $user = Auth::user();
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $originalName = $file->getClientOriginalName();
        $fileSize = $file->getSize();

        // Nama file rapi & terstruktur: {kategori}_{slug_guru}_{semester}_{timestamp}_{random}.{ext}
        $safeYear = Str::slug($request->academic_year);
        $fileName = sprintf(
            '%s_%s_%s_%s_%s.%s',
            $request->category,
            Str::slug($user->name),
            $request->semester,
            time(),
            Str::random(4),
            $extension
        );

        // Path folder: {tahun_ajaran}/{semester}/{user_id}/
        $folder = sprintf('%s/%s/%s', $safeYear, $request->semester, $user->id);

        $storedPath = $file->storeAs($folder, $fileName, 'administrasi_guru');

        TeacherAdministration::create([
            'user_id'        => $user->id,
            'academic_year'  => $request->academic_year,
            'semester'       => $request->semester,
            'grade_level'    => $request->grade_level,
            'subject'        => $request->subject,
            'category'       => $request->category,
            'title'          => $request->title,
            'description'    => $request->description,
            'file_path'      => $storedPath,
            'file_name'      => $originalName,
            'file_extension' => $extension,
            'file_size'      => $fileSize,
            'status'         => 'menunggu_verifikasi',
        ]);

        return redirect()->back()->with('success', 'Berkas administrasi guru berhasil diunggah.');
    }

    /**
     * Perbarui data atau unggah berkas perbaikan (revisi).
     */
    public function update(Request $request, TeacherAdministration $teacherAdministration)
    {
        $user = Auth::user();
        if ($teacherAdministration->user_id !== $user->id && !$user->hasAnyRole(['superadmin', 'admin'])) {
            abort(403, 'Anda tidak memiliki hak untuk mengubah berkas ini.');
        }

        $validated = $request->validate([
            'grade_level'   => 'nullable|string|max:50',
            'subject'       => 'nullable|string|max:100',
            'category'      => 'required|string|max:50',
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string|max:1000',
            'file'          => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar|max:25600',
        ]);

        $updateData = [
            'grade_level' => $request->grade_level,
            'subject'     => $request->subject,
            'category'    => $request->category,
            'title'       => $request->title,
            'description' => $request->description,
        ];

        // Jika guru mengunggah berkas pengganti
        if ($request->hasFile('file')) {
            // Hapus file lama jika ada
            if (Storage::disk('administrasi_guru')->exists($teacherAdministration->file_path)) {
                Storage::disk('administrasi_guru')->delete($teacherAdministration->file_path);
            }

            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            $safeYear = Str::slug($teacherAdministration->academic_year);
            $fileName = sprintf(
                '%s_%s_%s_%s_%s.%s',
                $request->category,
                Str::slug($user->name),
                $teacherAdministration->semester,
                time(),
                Str::random(4),
                $extension
            );

            $folder = sprintf('%s/%s/%s', $safeYear, $teacherAdministration->semester, $user->id);
            $storedPath = $file->storeAs($folder, $fileName, 'administrasi_guru');

            $updateData['file_path'] = $storedPath;
            $updateData['file_name'] = $file->getClientOriginalName();
            $updateData['file_extension'] = $extension;
            $updateData['file_size'] = $file->getSize();

            // Reset status verifikasi kembali ke menunggu_verifikasi saat file direvisi
            $updateData['status'] = 'menunggu_verifikasi';
        }

        $teacherAdministration->update($updateData);

        return redirect()->back()->with('success', 'Berkas administrasi guru berhasil diperbarui.');
    }

    /**
     * Hapus berkas administrasi guru.
     */
    public function destroy(TeacherAdministration $teacherAdministration)
    {
        $user = Auth::user();
        if ($teacherAdministration->user_id !== $user->id && !$user->hasAnyRole(['superadmin', 'admin'])) {
            abort(403, 'Anda tidak memiliki hak untuk menghapus berkas ini.');
        }

        if (Storage::disk('administrasi_guru')->exists($teacherAdministration->file_path)) {
            Storage::disk('administrasi_guru')->delete($teacherAdministration->file_path);
        }

        $teacherAdministration->delete();

        return redirect()->back()->with('success', 'Berkas administrasi guru berhasil dihapus.');
    }

    /**
     * Pratinjau berkas secara inline (khususnya PDF).
     */
    public function preview(TeacherAdministration $teacherAdministration)
    {
        $user = Auth::user();
        $canAccess = $teacherAdministration->user_id === $user->id || $user->hasAnyRole(['superadmin', 'admin', 'kepala_sekolah']);

        if (!$canAccess) {
            abort(403, 'Anda tidak memiliki akses ke berkas ini.');
        }

        $disk = Storage::disk('administrasi_guru');
        if (!$disk->exists($teacherAdministration->file_path)) {
            abort(404, 'File berkas tidak ditemukan di sistem penyimpanan.');
        }

        $mimeType = $disk->mimeType($teacherAdministration->file_path);
        $headers = [
            'Content-Type'        => $mimeType ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . $teacherAdministration->file_name . '"',
        ];

        return $disk->response($teacherAdministration->file_path, $teacherAdministration->file_name, $headers);
    }

    /**
     * Unduh berkas fisik asli.
     */
    public function download(TeacherAdministration $teacherAdministration)
    {
        $user = Auth::user();
        $canAccess = $teacherAdministration->user_id === $user->id || $user->hasAnyRole(['superadmin', 'admin', 'kepala_sekolah']);

        if (!$canAccess) {
            abort(403, 'Anda tidak memiliki akses ke berkas ini.');
        }

        $disk = Storage::disk('administrasi_guru');
        if (!$disk->exists($teacherAdministration->file_path)) {
            abort(404, 'File berkas tidak ditemukan di sistem penyimpanan.');
        }

        return $disk->download($teacherAdministration->file_path, $teacherAdministration->file_name);
    }
}
