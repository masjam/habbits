<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\TeacherJournal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PrivateMediaController extends Controller
{
    private function streamFile(string $diskPath): Response
    {
        // Sanitize path against directory traversal
        if (str_contains($diskPath, '..')) {
            abort(400, 'Invalid path');
        }

        if (Storage::disk('private')->exists($diskPath)) {
            return Storage::disk('private')->response($diskPath);
        }

        if (Storage::disk('public')->exists($diskPath)) {
            return Storage::disk('public')->response($diskPath);
        }

        abort(404, 'File tidak ditemukan.');
    }

    public function serveAttendanceAttachment(Request $request, string $filename)
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        $fullPath = 'attendance/attachments/' . $filename;

        $isAuthorized = $user->hasAnyRole(['admin', 'superadmin', 'kepala_sekolah'])
            || $user->isActualSuperadmin()
            || Attendance::where('attachment', $fullPath)->where('user_id', $user->id)->exists();

        if (!$isAuthorized) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat lampiran ini.');
        }

        return $this->streamFile($fullPath);
    }

    public function servePermitAttachment(Request $request, string $filename)
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        $fullPath = 'attachments/permits/' . $filename;

        $isAuthorized = $user->hasAnyRole(['admin', 'superadmin', 'kepala_sekolah'])
            || $user->isActualSuperadmin()
            || Attendance::where('attachment', $fullPath)->where('user_id', $user->id)->exists();

        if (!$isAuthorized) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat lampiran ini.');
        }

        return $this->streamFile($fullPath);
    }

    public function serveAttendancePhoto(Request $request, string $filename)
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        $fullPath = 'attendances/' . $filename;

        $isAuthorized = $user->hasAnyRole(['admin', 'superadmin', 'kepala_sekolah'])
            || $user->isActualSuperadmin()
            || Attendance::where(function ($q) use ($fullPath) {
                $q->where('photo_in', $fullPath)
                  ->orWhere('photo_out', $fullPath);
            })->where('user_id', $user->id)->exists();

        if (!$isAuthorized) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat foto presensi ini.');
        }

        return $this->streamFile($fullPath);
    }

    public function serveJournalPhoto(Request $request, string $filename)
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        $fullPath = 'journals/' . $filename;

        $isAuthorized = $user->hasAnyRole(['admin', 'superadmin', 'kepala_sekolah', 'kurikulum'])
            || $user->isActualSuperadmin()
            || TeacherJournal::where('photo', $fullPath)->where('user_id', $user->id)->exists();

        if (!$isAuthorized) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat foto jurnal ini.');
        }

        return $this->streamFile($fullPath);
    }
}
