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
        $validated = $this->validateSchedule($request);

        SpecialSchedule::create($validated);

        return redirect()->back()->with('success', 'Jadwal khusus berhasil ditambahkan.');
    }

    public function update(Request $request, SpecialSchedule $specialSchedule)
    {
        $validated = $this->validateSchedule($request);

        $specialSchedule->update($validated);

        return redirect()->back()->with('success', 'Jadwal khusus berhasil diperbarui.');
    }

    private function validateSchedule(Request $request): array
    {
        foreach (['time_in', 'time_out', 'piket_time_in', 'piket_time_out'] as $field) {
            if ($request->filled($field) && strlen($request->input($field)) > 5) {
                $request->merge([$field => substr($request->input($field), 0, 5)]);
            }
        }

        return $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'time_in' => 'required|date_format:H:i',
            'time_out' => 'required|date_format:H:i',
            'piket_time_in' => 'nullable|date_format:H:i',
            'piket_time_out' => 'nullable|date_format:H:i',
            'late_tolerance' => 'nullable|integer|min:0',
            'latitude' => 'nullable|string',
            'longitude' => 'nullable|string',
            'radius' => 'nullable|integer|min:10',
            'is_active' => 'boolean',
        ]);
    }

    public function destroy(SpecialSchedule $specialSchedule)
    {
        $specialSchedule->delete();
        return redirect()->back()->with('success', 'Jadwal khusus berhasil dihapus.');
    }
}
