<?php

namespace App\Http\Controllers\Corporate;

use App\Http\Controllers\Controller;

use App\Models\EmployeeDocument;
use App\Notifications\EmployeeDocumentStatusNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class EmployeeDocumentController extends Controller
{
    /**
     * Display a listing of the user's documents.
     */
    public function index()
    {
        $user = Auth::user();
        $documents = EmployeeDocument::where('user_id', $user->id)->latest()->get();
        return Inertia::render('Employee/Documents', [
            'documents' => $documents
        ]);
    }

    /**
     * Display a listing of ALL documents for HR/Admin.
     */
    public function manage(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['superadmin', 'admin'])) {
            abort(403, 'Unauthorized action.');
        }

        $status = $request->input('status', 'all');
        $search = $request->input('search');

        $query = EmployeeDocument::with('user:id,name,nip,divisi')->latest();

        // Filter status verifikasi: verified, pending, rejected
        if ($status && $status !== 'all') {
            if ($status === 'verified') {
                $query->where(function ($q) {
                    $q->where('status', 'verified')
                      ->orWhere(fn ($sq) => $sq->whereNull('status')->where('is_verified', true));
                });
            } elseif ($status === 'rejected') {
                $query->where(function ($q) {
                    $q->where('status', 'rejected')
                      ->orWhere(fn ($sq) => $sq->whereNull('status')->where('is_verified', false)->whereNotNull('notes')->where('notes', '!=', ''));
                });
            } elseif ($status === 'pending') {
                $query->where(function ($q) {
                    $q->where('status', 'pending')
                      ->orWhere(fn ($sq) => $sq->whereNull('status')->where('is_verified', false)->where(fn ($ssq) => $ssq->whereNull('notes')->orWhere('notes', '')));
                });
            }
        }

        // Filter pencarian teks
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('document_type', 'like', "%{$search}%")
                  ->orWhere('document_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('nip', 'like', "%{$search}%")
                         ->orWhere('divisi', 'like', "%{$search}%");
                  });
            });
        }

        $allDocs = (clone $query)->get();

        // Helper resolver status dokumen
        $resolveStatus = function ($doc) {
            if ($doc->status) {
                return $doc->status;
            }
            if ($doc->is_verified) {
                return 'verified';
            }
            return !empty($doc->notes) ? 'rejected' : 'pending';
        };

        // Grouping dokumen berdasarkan pegawai
        $groupedByUser = $allDocs->groupBy('user_id');
        $groupedEmployees = [];

        foreach ($groupedByUser as $userId => $docs) {
            $empUser = $docs->first()->user;
            if (!$empUser) continue;

            $verifiedCount = $docs->filter(fn ($d) => $resolveStatus($d) === 'verified')->count();
            $pendingCount  = $docs->filter(fn ($d) => $resolveStatus($d) === 'pending')->count();
            $rejectedCount = $docs->filter(fn ($d) => $resolveStatus($d) === 'rejected')->count();

            $groupedEmployees[] = [
                'user_id'          => $empUser->id,
                'name'             => $empUser->name,
                'nip'              => $empUser->nip,
                'divisi'           => $empUser->divisi,
                'total_docs'       => $docs->count(),
                'verified_count'   => $verifiedCount,
                'pending_count'    => $pendingCount,
                'rejected_count'   => $rejectedCount,
                'unverified_count' => $pendingCount + $rejectedCount,
                'documents'        => $docs->values(),
            ];
        }

        // Urutkan nama pegawai secara alfabetis
        usort($groupedEmployees, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        // Statistik dokumen sistem
        $stats = [
            'total_all'      => EmployeeDocument::count(),
            'total_verified' => EmployeeDocument::where('status', 'verified')->orWhere(fn ($q) => $q->whereNull('status')->where('is_verified', true))->count(),
            'total_pending'  => EmployeeDocument::where('status', 'pending')->orWhere(fn ($q) => $q->whereNull('status')->where('is_verified', false)->where(fn ($sq) => $sq->whereNull('notes')->orWhere('notes', '')))->count(),
            'total_rejected' => EmployeeDocument::where('status', 'rejected')->orWhere(fn ($q) => $q->whereNull('status')->where('is_verified', false)->whereNotNull('notes')->where('notes', '!=', ''))->count(),
            'total_employees'=> EmployeeDocument::distinct()->pluck('user_id')->count(),
        ];

        $documents = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Documents/Index', [
            'documents'        => $documents,
            'groupedEmployees' => $groupedEmployees,
            'stats'            => $stats,
            'filters'          => [
                'status' => $status,
                'search' => $search,
            ]
        ]);
    }
    /**
     * Store a newly created document in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'document_type' => 'required|string',
            'title' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:100',
            'document_year' => 'nullable|string|max:4',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048', // max 2MB
        ]);

        $user = Auth::user();
        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        
        // Buat nama file rapi: IDUser_TipeDokumen_Tahun_RandomString
        $safeType = Str::slug($request->document_type);
        $fileName = $user->id . '_' . $safeType . '_' . date('YmdHis') . '_' . Str::random(5) . '.' . $extension;
        
        // Simpan ke storage 'local' (private) agar lebih aman dan tidak bisa diakses langsung via URL publik
        $path = $file->storeAs('employee_documents/' . $user->id, $fileName, 'local');

        EmployeeDocument::create([
            'user_id' => $user->id,
            'document_type' => $request->document_type,
            'title' => $request->title,
            'document_number' => $request->document_number,
            'document_year' => $request->document_year,
            'file_path' => $path,
            'file_extension' => strtolower($extension),
            'status' => 'pending',
            'is_verified' => false,
        ]);

        return redirect()->back()->with('success', 'Dokumen berhasil diunggah.');
    }

    /**
     * Download the specified document.
     */
    public function download(EmployeeDocument $employeeDocument)
    {
        $user = Auth::user();
        // Hanya pemilik dokumen atau admin yang berhak mengunduh
        if ($employeeDocument->user_id !== $user->id && !$user->hasAnyRole(['superadmin', 'admin'])) {
            abort(403, 'Unauthorized action.');
        }

        $downloadName = ($employeeDocument->title ?: 'dokumen') . '.' . ($employeeDocument->file_extension ?: 'pdf');

        return $this->respondWithFile($employeeDocument->file_path, $downloadName, true);
    }

    /**
     * Preview the specified document in browser.
     */
    public function preview(EmployeeDocument $employeeDocument)
    {
        $user = Auth::user();
        // Hanya pemilik dokumen atau admin yang berhak melihat
        if ($employeeDocument->user_id !== $user->id && !$user->hasAnyRole(['superadmin', 'admin'])) {
            abort(403, 'Unauthorized action.');
        }

        return $this->respondWithFile($employeeDocument->file_path, null, false);
    }

    /**
     * Remove the specified document from storage.
     */
    public function destroy(EmployeeDocument $employeeDocument)
    {
        $user = Auth::user();
        // Hanya pemilik atau admin yang bisa hapus
        if ($employeeDocument->user_id !== $user->id && !$user->hasAnyRole(['superadmin', 'admin'])) {
            abort(403, 'Unauthorized action.');
        }

        // Hapus file fisik dari seluruh kemungkinan lokasi
        $this->deleteFileFromAnywhere($employeeDocument->file_path);

        $employeeDocument->delete();

        return redirect()->back()->with('success', 'Dokumen berhasil dihapus.');
    }

    /**
     * Resolve the storage response for preview or download across private, public, and legacy locations.
     */
    private function respondWithFile(string $filePath, ?string $downloadName = null, bool $isDownload = false)
    {
        // Path traversal defense
        if (str_contains($filePath, '..')) {
            abort(400, 'Invalid path');
        }

        // 1. Cek disk 'local' (private)
        if (Storage::disk('local')->exists($filePath)) {
            return $isDownload
                ? Storage::disk('local')->download($filePath, $downloadName)
                : Storage::disk('local')->response($filePath);
        }

        // 2. Cek disk 'private'
        if (Storage::disk('private')->exists($filePath)) {
            return $isDownload
                ? Storage::disk('private')->download($filePath, $downloadName)
                : Storage::disk('private')->response($filePath);
        }

        // 3. Cek disk 'public'
        if (Storage::disk('public')->exists($filePath)) {
            return $isDownload
                ? Storage::disk('public')->download($filePath, $downloadName)
                : Storage::disk('public')->response($filePath);
        }

        // 4. Cek legacy direktori storage/app/
        $legacyAppPath = storage_path('app/' . ltrim($filePath, '/\\'));
        if (file_exists($legacyAppPath) && !is_dir($legacyAppPath)) {
            return $isDownload
                ? response()->download($legacyAppPath, $downloadName)
                : response()->file($legacyAppPath);
        }

        // 5. Cek langsung storage_path()
        $directStoragePath = storage_path(ltrim($filePath, '/\\'));
        if (file_exists($directStoragePath) && !is_dir($directStoragePath)) {
            return $isDownload
                ? response()->download($directStoragePath, $downloadName)
                : response()->file($directStoragePath);
        }

        // 6. Cek jika prefix "public/" atau "private/" ikut tersimpan di DB
        $stripped = preg_replace('#^(public|private)/#', '', ltrim($filePath, '/\\'));
        if ($stripped !== $filePath) {
            if (Storage::disk('local')->exists($stripped)) {
                return $isDownload
                    ? Storage::disk('local')->download($stripped, $downloadName)
                    : Storage::disk('local')->response($stripped);
            }
            if (Storage::disk('public')->exists($stripped)) {
                return $isDownload
                    ? Storage::disk('public')->download($stripped, $downloadName)
                    : Storage::disk('public')->response($stripped);
            }
            $legacyStripped = storage_path('app/' . $stripped);
            if (file_exists($legacyStripped) && !is_dir($legacyStripped)) {
                return $isDownload
                    ? response()->download($legacyStripped, $downloadName)
                    : response()->file($legacyStripped);
            }
        }

        abort(404, 'File not found.');
    }

    /**
     * Delete file from any disk or legacy path.
     */
    private function deleteFileFromAnywhere(string $filePath): void
    {
        if (str_contains($filePath, '..')) {
            return;
        }

        if (Storage::disk('local')->exists($filePath)) {
            Storage::disk('local')->delete($filePath);
        }
        if (Storage::disk('private')->exists($filePath)) {
            Storage::disk('private')->delete($filePath);
        }
        if (Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }

        $legacyAppPath = storage_path('app/' . ltrim($filePath, '/\\'));
        if (file_exists($legacyAppPath) && !is_dir($legacyAppPath)) {
            @unlink($legacyAppPath);
        }

        $stripped = preg_replace('#^(public|private)/#', '', ltrim($filePath, '/\\'));
        if ($stripped !== $filePath) {
            if (Storage::disk('local')->exists($stripped)) {
                Storage::disk('local')->delete($stripped);
            }
            if (Storage::disk('public')->exists($stripped)) {
                Storage::disk('public')->delete($stripped);
            }
            $legacyStripped = storage_path('app/' . $stripped);
            if (file_exists($legacyStripped) && !is_dir($legacyStripped)) {
                @unlink($legacyStripped);
            }
        }
    }
    
    /**
     * Verifikasi dokumen oleh HR/Admin
     */
    public function verify(Request $request, EmployeeDocument $employeeDocument)
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['superadmin', 'admin'])) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'status' => 'nullable|string|in:verified,pending,rejected',
            'is_verified' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($request->has('status')) {
            $status = $request->status;
            $isVerified = ($status === 'verified');
        } else {
            $isVerified = (bool) $request->is_verified;
            $status = $isVerified ? 'verified' : ($request->notes ? 'rejected' : 'pending');
        }

        $employeeDocument->update([
            'status' => $status,
            'is_verified' => $isVerified,
            'notes' => $request->notes
        ]);

        // Kirim notifikasi ke pegawai pemilik arsip
        $owner = $employeeDocument->user;
        if ($owner) {
            $notes = $request->notes;
            dispatch(function () use ($owner, $employeeDocument, $status, $notes) {
                try {
                    $owner->notify(new EmployeeDocumentStatusNotification(
                        $employeeDocument,
                        $status,
                        $notes
                    ));
                } catch (\Throwable $e) {
                    Log::warning('Gagal kirim notifikasi status dokumen: ' . $e->getMessage());
                }
            })->afterResponse();
        }

        return redirect()->back()->with('success', 'Status dokumen berhasil diperbarui dan notifikasi telah dikirim ke pegawai.');
    }
}
