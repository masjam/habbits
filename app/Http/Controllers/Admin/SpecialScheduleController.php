<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpecialSchedule;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SpecialScheduleController extends Controller
{
    public function index()
    {
        $schedules = SpecialSchedule::orderBy('start_date', 'desc')->get();
        return Inertia::render('Admin/SpecialSchedules/Index', [
            'schedules' => $schedules
        ]);
    }

    public function store(Request $request)
    {
        // Pastikan format jam masuk & keluar dipotong ke HH:mm jika menyertakan detik
        if ($request->filled('time_in') && strlen($request->time_in) > 5) {
            $request->merge(['time_in' => substr($request->time_in, 0, 5)]);
        }
        if ($request->filled('time_out') && strlen($request->time_out) > 5) {
            $request->merge(['time_out' => substr($request->time_out, 0, 5)]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'time_in' => 'required|date_format:H:i',
            'time_out' => 'required|date_format:H:i',
            'late_tolerance' => 'nullable|integer|min:0',
            'latitude' => 'nullable|string',
            'longitude' => 'nullable|string',
            'radius' => 'nullable|integer|min:10',
            'is_active' => 'boolean',
        ]);

        SpecialSchedule::create($validated);

        return redirect()->back()->with('success', 'Jadwal khusus berhasil ditambahkan.');
    }

    public function update(Request $request, SpecialSchedule $specialSchedule)
    {
        // Pastikan format jam masuk & keluar dipotong ke HH:mm jika menyertakan detik
        if ($request->filled('time_in') && strlen($request->time_in) > 5) {
            $request->merge(['time_in' => substr($request->time_in, 0, 5)]);
        }
        if ($request->filled('time_out') && strlen($request->time_out) > 5) {
            $request->merge(['time_out' => substr($request->time_out, 0, 5)]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'time_in' => 'required|date_format:H:i',
            'time_out' => 'required|date_format:H:i',
            'late_tolerance' => 'nullable|integer|min:0',
            'latitude' => 'nullable|string',
            'longitude' => 'nullable|string',
            'radius' => 'nullable|integer|min:10',
            'is_active' => 'boolean',
        ]);

        $specialSchedule->update($validated);

        return redirect()->back()->with('success', 'Jadwal khusus berhasil diperbarui.');
    }

    public function destroy(SpecialSchedule $specialSchedule)
    {
        $specialSchedule->delete();
        return redirect()->back()->with('success', 'Jadwal khusus berhasil dihapus.');
    }
}
