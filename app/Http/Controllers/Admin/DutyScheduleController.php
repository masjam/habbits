<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DutySchedule;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DutyScheduleController extends Controller
{
    /**
     * Tampilkan pemetaan jadwal piket 7 hari beserta daftar pegawai.
     */
    public function index()
    {
        // Ambil jadwal piket yang sudah tersimpan dengan relasi user
        $schedules = DutySchedule::with(['users:id,name,email,avatar,divisi,nip'])
            ->orderBy('day_of_week')
            ->get()
            ->keyBy('day_of_week');

        // Daftar semua pegawai aktif untuk dipilih
        $employees = User::select('id', 'name', 'email', 'avatar', 'divisi', 'nip')
            ->orderBy('name')
            ->get();

        // 7 Hari standar
        $dayNames = DutySchedule::getDayNames();
        $daysList = [];

        foreach ($dayNames as $dayNum => $dayName) {
            $existing = $schedules->get($dayNum);
            $daysList[] = [
                'day_of_week'    => $dayNum,
                'day_name'       => $dayName,
                'schedule_id'    => $existing?->id,
                'name'           => $existing?->name ?? "Piket $dayName",
                'time_in'        => $existing?->time_in ?? '06:30',
                'time_out'       => $existing?->time_out ?? '14:30',
                'late_tolerance' => $existing?->late_tolerance,
                'is_active'      => $existing ? $existing->is_active : false,
                'notes'          => $existing?->notes ?? '',
                'users'          => $existing?->users ?? [],
                'user_ids'       => $existing ? $existing->users->pluck('id')->toArray() : [],
            ];
        }

        // Pengaturan jam kerja harian default sekolah
        $dailySchedulesRaw = Setting::where('key', 'presensi_daily_schedules')->value('value');
        $defaultStart = Setting::where('key', 'presensi_work_start')->value('value') ?: '07:00';
        $defaultEnd   = Setting::where('key', 'presensi_work_end')->value('value') ?: '15:00';
        $defaultTol   = (int)(Setting::where('key', 'presensi_late_tolerance')->value('value') ?: 15);

        $dailyWorkSchedules = [];
        $decodedDaily = $dailySchedulesRaw ? json_decode($dailySchedulesRaw, true) : [];

        foreach ($dayNames as $dayNum => $dayName) {
            $saved = $decodedDaily[$dayNum] ?? null;
            $dailyWorkSchedules[$dayNum] = [
                'day_of_week' => $dayNum,
                'day_name'    => $dayName,
                'is_active'   => $saved['is_active'] ?? ($dayNum <= 5), // default Senin-Jumat aktif
                'start'       => $saved['start'] ?? ($dayNum === 5 ? '07:00' : $defaultStart),
                'end'         => $saved['end'] ?? ($dayNum === 5 ? '11:30' : ($dayNum === 6 ? '13:00' : $defaultEnd)),
                'tolerance'   => $saved['tolerance'] ?? $defaultTol,
            ];
        }

        return Inertia::render('Admin/DutySchedules/Index', [
            'daysList'           => $daysList,
            'employees'          => $employees,
            'dailyWorkSchedules' => $dailyWorkSchedules,
            'globalWorkStart'    => $defaultStart,
            'globalWorkEnd'      => $defaultEnd,
            'globalTolerance'    => $defaultTol,
        ]);
    }

    /**
     * Simpan / perbarui jadwal piket suatu hari beserta daftar pegawai yang ditugaskan.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'day_of_week'    => 'required|integer|between:1,7',
            'name'           => 'required|string|max:100',
            'time_in'        => 'required|string|max:10',
            'time_out'       => 'required|string|max:10',
            'late_tolerance' => 'nullable|integer|min:0|max:120',
            'is_active'      => 'boolean',
            'notes'          => 'nullable|string|max:500',
            'user_ids'       => 'nullable|array',
            'user_ids.*'     => 'exists:users,id',
        ]);

        $schedule = DutySchedule::updateOrCreate(
            ['day_of_week' => $validated['day_of_week']],
            [
                'name'           => $validated['name'],
                'time_in'        => $validated['time_in'],
                'time_out'       => $validated['time_out'],
                'late_tolerance' => $validated['late_tolerance'],
                'is_active'      => $validated['is_active'] ?? true,
                'notes'          => $validated['notes'] ?? null,
            ]
        );

        $schedule->users()->sync($validated['user_ids'] ?? []);

        return redirect()->back()->with('success', "Jadwal piket hari {$schedule->day_name} berhasil disimpan.");
    }

    /**
     * Hapus jadwal piket.
     */
    public function destroy(DutySchedule $dutySchedule)
    {
        $dayName = $dutySchedule->day_name;
        $dutySchedule->users()->detach();
        $dutySchedule->delete();

        return redirect()->back()->with('success', "Jadwal piket hari {$dayName} berhasil dihapus.");
    }

    /**
     * Simpan jam kerja harian default sekolah (Senin - Minggu).
     */
    public function saveDailyWorkSchedules(Request $request)
    {
        $request->validate([
            'schedules' => 'required|array',
        ]);

        Setting::updateOrCreate(
            ['key' => 'presensi_daily_schedules'],
            ['value' => json_encode($request->schedules)]
        );

        return redirect()->back()->with('success', 'Pengaturan jam kerja harian berhasil diperbarui.');
    }
}
