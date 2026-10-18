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
        $selectedGradeLevel = $request->input('grade_level', 'all');
        $search = $request->input('search');

        // Daftar kelas yang tersedia (standar SD 1-6 + Umum)
        $standardClasses = [
            'Kelas 1',
            'Kelas 2',
            'Kelas 3',
            'Kelas 4',
            'Kelas 5',
            'Kelas 6',
            'Umum',
        ];

        $dbClasses = TeacherAdministration::whereNotNull('grade_level')
            ->where('grade_level', '!=', '')
            ->distinct()
            ->pluck('grade_level')
            ->toArray();

        $classesList = array_values(array_unique(array_merge($standardClasses, $dbClasses)));

        // Base query untuk daftar berkas
        $query = TeacherAdministration::query()
            ->with(['user:id,name,nip,divisi', 'reviewer:id,name'])
            ->orderByRaw("CASE WHEN status = 'menunggu_verifikasi' THEN 1 WHEN status = 'perlu_revisi' THEN 2 ELSE 3 END")
            ->latest('id');

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

        if ($selectedGradeLevel && $selectedGradeLevel !== 'all') {
            if ($selectedGradeLevel === 'Umum') {
                $query->where(function ($q) {
                    $q->where('grade_level', 'Umum')
                      ->orWhere('grade_level', 'like', '%Umum%')
                      ->orWhereNull('grade_level')
                      ->orWhere('grade_level', '');
                });
            } else {
                $query->where('grade_level', $selectedGradeLevel);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('grade_level', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Dokumen paginasi untuk tampilan tabel flat jika diinginkan
        $documents = (clone $query)->paginate(20)->withQueryString();

        // Ambil seluruh dokumen hasil filter untuk struktur hirarki Guru -> Kategori -> Dokumen
        $allFilteredDocs = (clone $query)->get();

        // Grouping dokumen berdasarkan Guru -> Kategori
        $groupedByUser = $allFilteredDocs->groupBy('user_id');
        $teacherGroups = [];

        foreach ($groupedByUser as $userId => $userDocs) {
            $user = $userDocs->first()->user;
            if (!$user) {
                continue;
            }

            $groupedByCat = $userDocs->groupBy('category');
            $categoriesData = [];

            foreach ($groupedByCat as $catKey => $catDocs) {
                $categoriesData[] = [
                    'category_key'   => $catKey,
                    'category_label' => TeacherAdministration::CATEGORIES[$catKey] ?? ucfirst(str_replace('_', ' ', $catKey)),
                    'total_docs'     => $catDocs->count(),
                    'pending_count'  => $catDocs->where('status', 'menunggu_verifikasi')->count(),
                    'docs'           => $catDocs->values(),
                ];
            }

            $teacherGroups[] = [
                'user_id'       => $user->id,
                'name'          => $user->name,
                'nip'           => $user->nip,
                'divisi'        => $user->divisi,
                'total_docs'    => $userDocs->count(),
                'pending_count' => $userDocs->where('status', 'menunggu_verifikasi')->count(),
                'categories'    => $categoriesData,
            ];
        }

        // Urutkan: Guru yang memiliki berkas menunggu telaah berada paling atas, selanjutnya urut abjad
        usort($teacherGroups, function ($a, $b) {
            if ($a['pending_count'] !== $b['pending_count']) {
                return $b['pending_count'] <=> $a['pending_count'];
            }
            return strcasecmp($a['name'], $b['name']);
        });

        // Hitung statistik per Tab Kelas
        $tabSummaryQuery = TeacherAdministration::query();
        if ($selectedYear && $selectedYear !== 'all') {
            $tabSummaryQuery->where('academic_year', $selectedYear);
        }
        if ($selectedSemester && $selectedSemester !== 'all') {
            $tabSummaryQuery->where('semester', $selectedSemester);
        }
        if ($selectedUserId && $selectedUserId !== 'all') {
            $tabSummaryQuery->where('user_id', $selectedUserId);
        }
        if ($selectedCategory && $selectedCategory !== 'all') {
            $tabSummaryQuery->where('category', $selectedCategory);
        }

        $allSummaryDocs = (clone $tabSummaryQuery)->get(['id', 'grade_level', 'status']);
        $classStats = [];
        foreach ($classesList as $cls) {
            $classStats[$cls] = [
                'total'   => 0,
                'pending' => 0,
            ];
        }

        foreach ($allSummaryDocs as $doc) {
            $grade = trim((string)$doc->grade_level);
            if ($grade === '' || strcasecmp($grade, 'umum') === 0) {
                $targetClass = 'Umum';
            } elseif (in_array($grade, $classesList)) {
                $targetClass = $grade;
            } else {
                $targetClass = 'Umum';
            }

            if (!isset($classStats[$targetClass])) {
                $classStats[$targetClass] = ['total' => 0, 'pending' => 0];
            }
            $classStats[$targetClass]['total']++;
            if ($doc->status === 'menunggu_verifikasi') {
                $classStats[$targetClass]['pending']++;
            }
        }

        $totalAll = $allSummaryDocs->count();
        $totalAllPending = $allSummaryDocs->where('status', 'menunggu_verifikasi')->count();

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
            'teacherGroups'       => $teacherGroups,
            'teachers'            => $teachers,
            'matrix'              => $matrix,
            'categories'          => TeacherAdministration::CATEGORIES,
            'classesList'         => $classesList,
            'classStats'          => $classStats,
            'totalAll'            => $totalAll,
            'totalAllPending'     => $totalAllPending,
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
                'grade_level'   => $selectedGradeLevel,
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
