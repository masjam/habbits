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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'time_in' => 'required|date_format:H:i|nullable',
            'time_out' => 'required|date_format:H:i|nullable',
            'late_tolerance' => 'nullable|integer|min:0',
            'latitude' => 'nullable|string',
            'longitude' => 'nullable|string',
            'radius' => 'nullable|integer|min:10',
            'is_active' => 'boolean',
        ]);

        // Fix date_format parsing issues by checking if length is 5
        if (strlen($validated['time_in']) > 5) $validated['time_in'] = substr($validated['time_in'], 0, 5);
        if (strlen($validated['time_out']) > 5) $validated['time_out'] = substr($validated['time_out'], 0, 5);

        $specialSchedule->update($validated);

        return redirect()->back()->with('success', 'Jadwal khusus berhasil diperbarui.');
    }

    public function destroy(SpecialSchedule $specialSchedule)
    {
        $specialSchedule->delete();
        return redirect()->back()->with('success', 'Jadwal khusus berhasil dihapus.');
    }
}
