<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $query = EmployeeDocument::with('user')->latest();
        
        // Filter opsional: status verifikasi
        if ($request->has('status') && $request->status !== 'all') {
            $isVerified = $request->status === 'verified';
            $query->where('is_verified', $isVerified);
        }

        $documents = $query->paginate(15)->withQueryString();

        return Inertia::render('Admin/Documents/Index', [
            'documents' => $documents,
            'filters' => $request->only(['status'])
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

        if (!Storage::disk('local')->exists($employeeDocument->file_path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('local')->download($employeeDocument->file_path, $employeeDocument->title . '.' . $employeeDocument->file_extension);
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

        // Hapus file fisik
        if (Storage::disk('local')->exists($employeeDocument->file_path)) {
            Storage::disk('local')->delete($employeeDocument->file_path);
        }

        $employeeDocument->delete();

        return redirect()->back()->with('success', 'Dokumen berhasil dihapus.');
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
            'is_verified' => 'required|boolean',
            'notes' => 'nullable|string'
        ]);

        $employeeDocument->update([
            'is_verified' => $request->is_verified,
            'notes' => $request->notes
        ]);

        return redirect()->back()->with('success', 'Status dokumen berhasil diperbarui.');
    }
}
