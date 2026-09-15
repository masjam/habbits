<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
        ]);
    }

    public function hrIndex()
    {
        $user = auth()->user();
        $isSuperAdmin = $user && ($user->isActualSuperadmin() || $user->hasRole('superadmin'));

        if (!$isSuperAdmin) {
            abort(403, 'Hanya Superadmin yang dapat mengakses halaman Pengaturan HR & Fitur Lanjutan.');
        }

        $settings = Setting::all()->pluck('value', 'key')->toArray();
        [$photoStats, $cleaningOptions] = $this->getPhotoCleansingData();

        return Inertia::render('Admin/Settings/Hr', [
            'settings'        => $settings,
            'photoStats'      => $photoStats,
            'cleaningOptions' => $cleaningOptions,
        ]);
    }

    private function getPhotoCleansingData()
    {
        // Hitung statistik file foto presensi yang ada di storage
        $photoFiles = Storage::disk('public')->files('attendances');
        $totalFiles = count($photoFiles);
        $totalBytes = 0;
        foreach ($photoFiles as $file) {
            $totalBytes += Storage::disk('public')->size($file);
        }
        $totalMb = round($totalBytes / (1024 * 1024), 2);
        $formattedSize = $totalMb >= 1 ? "{$totalMb} MB" : round($totalBytes / 1024, 1) . " KB";

        $photoStats = [
            'total_files'          => $totalFiles,
            'total_bytes'          => $totalBytes,
            'total_size_formatted' => $formattedSize,
        ];

        // Buat 6 opsi pembersihan foto (1 s/d 6 bulan yang lalu)
        $cleaningOptions = [];
        for ($i = 1; $i <= 6; $i++) {
            $cutoff = Carbon::now()->startOfMonth()->subMonths($i)->endOfMonth();
            $cutoffDate = $cutoff->toDateString();
            $photoCount = Attendance::where('date', '<=', $cutoffDate)
                ->where(function ($q) {
                    $q->whereNotNull('photo_in')->orWhereNotNull('photo_out');
                })->count();

            $cleaningOptions[] = [
                'months'         => $i,
                'label'          => "{$i} Bulan lalu (s.d. " . $cutoff->locale('id')->isoFormat('D MMMM Y') . ")",
                'cutoff_date'    => $cutoffDate,
                'formatted_date' => $cutoff->locale('id')->isoFormat('D MMMM Y'),
                'photo_count'    => $photoCount,
            ];
        }

        return [$photoStats, $cleaningOptions];
    }

    public function update(Request $request)
    {
        $rules = [
            'announcement_text' => 'nullable|string',
            'popup_active' => 'boolean',
            'popup_text' => 'nullable|string',
            'youtube_link' => 'nullable|string',
            'running_text' => 'nullable|string',
        ];

        $isSuperAdmin = auth()->user()->isActualSuperadmin() || auth()->user()->hasRole('superadmin');

        if ($isSuperAdmin) {
            $rules['feature_presensi'] = 'boolean';
            $rules['presensi_latitude'] = 'nullable|string|max:50';
            $rules['presensi_longitude'] = 'nullable|string|max:50';
            $rules['presensi_radius_meters'] = 'nullable|numeric|min:10|max:10000';
            $rules['presensi_work_start'] = 'nullable|string|max:10';
            $rules['presensi_late_tolerance'] = 'nullable|numeric|min:0|max:120';
            $rules['presensi_work_end'] = 'nullable|string|max:10';

            $rules['gamification_active'] = 'boolean';
            $rules['push_notifications_active'] = 'boolean';
            $rules['dark_mode_active'] = 'boolean';
            $rules['auto_warning_active'] = 'boolean';
            $rules['custom_habit_divisions_active'] = 'boolean';
            $rules['feature_badges'] = 'boolean';
            $rules['feature_divisi'] = 'boolean';
            $rules['feature_cuti'] = 'boolean';
            $rules['feature_idcard'] = 'boolean';
            $rules['feature_notes'] = 'boolean';
            $rules['maintenance_mode'] = 'boolean';
            $rules['maintenance_title'] = 'nullable|string|max:255';
            $rules['maintenance_message'] = 'nullable|string';
            $rules['maintenance_end_time'] = 'nullable|string|max:100';
        }

        $data = $request->validate($rules);

        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return redirect()->back()->with('success', 'Pengaturan berhasil diperbarui.');
    }

    /**
     * Pembersihan File Foto Presensi (Khusus Superadmin)
     */
    public function cleanPhotos(Request $request)
    {
        $currentUser = auth()->user();
        if (!$currentUser->isActualSuperadmin() && !$currentUser->hasRole('superadmin')) {
            abort(403, 'Hanya Superadmin yang memiliki hak akses untuk melakukan pembersihan storage foto presensi.');
        }

        $data = $request->validate([
            'months' => 'required|integer|min:1|max:6',
        ]);

        $months = (int) $data['months'];
        $cutoff = Carbon::now()->startOfMonth()->subMonths($months)->endOfMonth();
        $cutoffDate = $cutoff->toDateString();

        $attendances = Attendance::where('date', '<=', $cutoffDate)
            ->where(function ($q) {
                $q->whereNotNull('photo_in')->orWhereNotNull('photo_out');
            })
            ->get();

        $deletedFiles = 0;
        $deletedBytes = 0;

        foreach ($attendances as $att) {
            if ($att->photo_in) {
                if (Storage::disk('public')->exists($att->photo_in)) {
                    $deletedBytes += Storage::disk('public')->size($att->photo_in);
                    Storage::disk('public')->delete($att->photo_in);
                    $deletedFiles++;
                }
                $att->photo_in = null;
            }
            if ($att->photo_out) {
                if (Storage::disk('public')->exists($att->photo_out)) {
                    $deletedBytes += Storage::disk('public')->size($att->photo_out);
                    Storage::disk('public')->delete($att->photo_out);
                    $deletedFiles++;
                }
                $att->photo_out = null;
            }
            $att->save();
        }

        $sizeMb = round($deletedBytes / (1024 * 1024), 2);
        $sizeText = $sizeMb >= 1 ? "{$sizeMb} MB" : round($deletedBytes / 1024, 1) . " KB";
        $formattedCutoff = $cutoff->locale('id')->isoFormat('D MMMM Y');

        return redirect()->back()->with('success', "Pembersihan berhasil! Sebanyak {$deletedFiles} file foto presensi (membebaskan {$sizeText} kapasitas storage) s.d. tanggal {$formattedCutoff} telah dihapus. Data riwayat presensi tetap tersimpan rapi.");
    }
}
