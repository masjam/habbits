<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings
        ]);
    }

    public function hrIndex()
    {
        if (!auth()->user()->hasRole('superadmin')) {
            abort(403, 'Hanya Superadmin yang dapat mengakses halaman ini.');
        }

        $settings = Setting::all()->pluck('value', 'key')->toArray();

        return Inertia::render('Admin/Settings/Hr', [
            'settings' => $settings
        ]);
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

        if (auth()->user()->hasRole('superadmin')) {
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
}
