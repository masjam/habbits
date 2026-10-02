<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TeacherJournal;
use App\Models\Setting;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TeacherJournalController extends Controller
{
    public function index()
    {
        $featureJurnal = Setting::where('key', 'feature_jurnal')->value('value') ?? 'true';
        if ($featureJurnal === 'false' || $featureJurnal === '0') {
            abort(403, 'Fitur Jurnal Harian dinonaktifkan.');
        }

        $user = Auth::user();
        if ($user->hasRole(['superadmin', 'admin'])) {
            abort(403, 'Admin dan Superadmin tidak diizinkan mengisi jurnal.');
        }
        
        $query = TeacherJournal::query()->with('user');
        
        // If not kepala_sekolah, only see their own journals
        if (!$user->hasRole(['kepala_sekolah'])) {
            $query->where('user_id', $user->id);
        }

        $journals = $query->orderBy('date', 'desc')->paginate(10);
        
        return Inertia::render('TeacherJournal/Index', [
            'journals' => $journals
        ]);
    }

    public function create()
    {
        return Inertia::render('TeacherJournal/Form');
    }

    public function store(Request $request)
    {
        $featureJurnal = Setting::where('key', 'feature_jurnal')->value('value') ?? 'true';
        if ($featureJurnal === 'false' || $featureJurnal === '0') {
            abort(403, 'Fitur Jurnal Harian dinonaktifkan.');
        }

        $user = Auth::user();
        if ($user->hasRole(['superadmin', 'admin'])) {
            abort(403, 'Admin dan Superadmin tidak diizinkan mengisi jurnal.');
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'class_name' => 'nullable|string|max:255',
            'subject' => 'nullable|string|max:255',
            'theme' => 'nullable|string|max:255',
            'meeting_number' => 'nullable|string|max:255',
            'period' => 'nullable|string|max:255',
            'time_start' => 'required|date_format:H:i',
            'time_end' => 'required|date_format:H:i',
            'material' => 'required|string',
            'notes' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
            'student_present' => 'nullable|integer|min:0',
            'student_sick' => 'nullable|integer|min:0',
            'student_leave' => 'nullable|integer|min:0',
            'student_absent' => 'nullable|integer|min:0',
        ]);

        $validated['student_present'] = $validated['student_present'] ?? 0;
        $validated['student_sick'] = $validated['student_sick'] ?? 0;
        $validated['student_leave'] = $validated['student_leave'] ?? 0;
        $validated['student_absent'] = $validated['student_absent'] ?? 0;

        $validated['user_id'] = Auth::id();

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('journals', 'public');
        }

        TeacherJournal::create($validated);

        return redirect()->route('teacher-journals.index')->with('success', 'Jurnal berhasil disimpan.');
    }

    public function edit(TeacherJournal $teacherJournal)
    {
        $user = Auth::user();
        if ($user->hasRole(['superadmin', 'admin'])) {
            abort(403, 'Admin dan Superadmin tidak diizinkan mengisi jurnal.');
        }
        if (!$user->hasRole(['kepala_sekolah']) && $teacherJournal->user_id !== $user->id) {
            abort(403);
        }

        return Inertia::render('TeacherJournal/Form', [
            'journal' => $teacherJournal
        ]);
    }

    public function update(Request $request, TeacherJournal $teacherJournal)
    {
        $user = Auth::user();
        if ($user->hasRole(['superadmin', 'admin'])) {
            abort(403, 'Admin dan Superadmin tidak diizinkan mengubah jurnal.');
        }
        if (!$user->hasRole(['kepala_sekolah']) && $teacherJournal->user_id !== $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'class_name' => 'nullable|string|max:255',
            'subject' => 'nullable|string|max:255',
            'theme' => 'nullable|string|max:255',
            'meeting_number' => 'nullable|string|max:255',
            'period' => 'nullable|string|max:255',
            'time_start' => 'required|date_format:H:i',
            'time_end' => 'required|date_format:H:i',
            'material' => 'required|string',
            'notes' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
            'student_present' => 'nullable|integer|min:0',
            'student_sick' => 'nullable|integer|min:0',
            'student_leave' => 'nullable|integer|min:0',
            'student_absent' => 'nullable|integer|min:0',
        ]);

        $validated['student_present'] = $validated['student_present'] ?? 0;
        $validated['student_sick'] = $validated['student_sick'] ?? 0;
        $validated['student_leave'] = $validated['student_leave'] ?? 0;
        $validated['student_absent'] = $validated['student_absent'] ?? 0;

        if ($request->hasFile('photo')) {
            if ($teacherJournal->photo) {
                Storage::disk('public')->delete($teacherJournal->photo);
            }
            $validated['photo'] = $request->file('photo')->store('journals', 'public');
        }

        $teacherJournal->update($validated);

        return redirect()->route('teacher-journals.index')->with('success', 'Jurnal berhasil diperbarui.');
    }

    public function destroy(TeacherJournal $teacherJournal)
    {
        $user = Auth::user();
        if ($user->hasRole(['superadmin', 'admin'])) {
            abort(403, 'Admin dan Superadmin tidak diizinkan menghapus jurnal.');
        }
        if (!$user->hasRole(['kepala_sekolah']) && $teacherJournal->user_id !== $user->id) {
            abort(403);
        }

        if ($teacherJournal->photo) {
            Storage::disk('public')->delete($teacherJournal->photo);
        }
        
        $teacherJournal->delete();

        return redirect()->route('teacher-journals.index')->with('success', 'Jurnal berhasil dihapus.');
    }
}
