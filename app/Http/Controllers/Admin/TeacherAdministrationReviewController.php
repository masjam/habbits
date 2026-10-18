<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\TeacherAdministration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class TeacherAdministrationReviewController extends Controller
{
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
     * Tampilkan rekap administrasi guru & matriks kelengkapan berkas untuk Admin & Kepala Sekolah.
     */
    public function index(Request $request)
    {
        [$currentAcademicYear, $currentSemester] = $this->getCurrentAcademicPeriod();

        $selectedYear = $request->input('academic_year', $currentAcademicYear);
        $selectedSemester = $request->input('semester', $currentSemester);
        $selectedUserId = $request->input('user_id');
        $selectedCategory = $request->input('category');
        $selectedStatus = $request->input('status');
        $search = $request->input('search');

        // Query Daftar Pengajuan Berkas
        $query = TeacherAdministration::query()
            ->with(['user:id,name,nip,divisi', 'reviewer:id,name'])
            ->latest();

        if ($selectedYear && $selectedYear !== 'all') {
            $query->where('academic_year', $selectedYear);
        }

        if ($selectedSemester && $selectedSemester !== 'all') {
            $query->where('semester', $selectedSemester);
        }

        if ($selectedUserId && $selectedUserId !== 'all') {
            $query->where('user_id', $selectedUserId);
        }

        if ($selectedCategory && $selectedCategory !== 'all') {
            $query->where('category', $selectedCategory);
        }

        if ($selectedStatus && $selectedStatus !== 'all') {
            $query->where('status', $selectedStatus);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $documents = $query->paginate(20)->withQueryString();

        // Daftar Guru (Kecuali superadmin murni)
        $teachers = User::whereDoesntHave('roles', fn($q) => $q->whereIn('name', ['superadmin']))
            ->select('id', 'name', 'nip', 'divisi')
            ->orderBy('name')
            ->get();

        // Hitung Matriks Kelengkapan Berkas per Guru untuk periode terpilih
        $matrixDocuments = TeacherAdministration::where('academic_year', $selectedYear)
            ->where('semester', $selectedSemester)
            ->get(['id', 'user_id', 'category', 'status', 'title']);

        $categoryKeys = array_keys(TeacherAdministration::CATEGORIES);
        $matrix = [];

        foreach ($teachers as $teacher) {
            $userDocs = $matrixDocuments->where('user_id', $teacher->id);
            $catStatuses = [];

            foreach ($categoryKeys as $catKey) {
                $docInCat = $userDocs->where('category', $catKey)->first();
                if ($docInCat) {
                    $catStatuses[$catKey] = [
                        'id'     => $docInCat->id,
                        'status' => $docInCat->status,
                        'title'  => $docInCat->title,
                    ];
                } else {
                    $catStatuses[$catKey] = null;
                }
            }

            $matrix[] = [
                'user_id'    => $teacher->id,
                'name'       => $teacher->name,
                'divisi'     => $teacher->divisi,
                'total_docs' => $userDocs->count(),
                'categories' => $catStatuses,
            ];
        }

        // Statistik keseluruhan periode
        $allPeriodDocs = TeacherAdministration::where('academic_year', $selectedYear)
            ->where('semester', $selectedSemester)
            ->get();

        $stats = [
            'total_berkas'       => $allPeriodDocs->count(),
            'total_disetujui'    => $allPeriodDocs->where('status', 'disetujui')->count(),
            'total_perlu_revisi' => $allPeriodDocs->where('status', 'perlu_revisi')->count(),
            'total_menunggu'     => $allPeriodDocs->where('status', 'menunggu_verifikasi')->count(),
        ];

        $yearsList = [
            '2024/2025',
            '2025/2026',
            '2026/2027',
            '2027/2028',
        ];
        if (!in_array($currentAcademicYear, $yearsList)) {
            $yearsList[] = $currentAcademicYear;
        }

        return Inertia::render('Admin/TeacherAdministration/Index', [
            'documents'           => $documents,
            'teachers'            => $teachers,
            'matrix'              => $matrix,
            'categories'          => TeacherAdministration::CATEGORIES,
            'stats'               => $stats,
            'yearsList'           => $yearsList,
            'currentAcademicYear' => $currentAcademicYear,
            'currentSemester'     => $currentSemester,
            'filters'             => [
                'academic_year' => $selectedYear,
                'semester'      => $selectedSemester,
                'user_id'       => $selectedUserId,
                'category'      => $selectedCategory,
                'status'        => $selectedStatus,
                'search'        => $search,
            ],
        ]);
    }

    /**
     * Verifikasi & Telaah berkas administrasi guru oleh Kepala Sekolah / Admin.
     */
    public function verify(Request $request, TeacherAdministration $teacherAdministration)
    {
        $request->validate([
            'status'   => 'required|in:disetujui,perlu_revisi,menunggu_verifikasi',
            'feedback' => 'nullable|string|max:1000',
        ]);

        $teacherAdministration->update([
            'status'      => $request->status,
            'feedback'    => $request->feedback,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        $statusLabel = $request->status === 'disetujui' ? 'disetujui' : ($request->status === 'perlu_revisi' ? 'diminta untuk direvisi' : 'diperbarui');

        return redirect()->back()->with('success', "Berkas '{$teacherAdministration->title}' berhasil ditelaah ({$statusLabel}).");
    }

    /**
     * Hapus berkas oleh Admin jika diperlukan.
     */
    public function destroy(TeacherAdministration $teacherAdministration)
    {
        if (Storage::disk('administrasi_guru')->exists($teacherAdministration->file_path)) {
            Storage::disk('administrasi_guru')->delete($teacherAdministration->file_path);
        }

        $teacherAdministration->delete();

        return redirect()->back()->with('success', 'Berkas administrasi guru berhasil dihapus oleh administrator.');
    }
}
