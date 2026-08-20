<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Habit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HabitController extends Controller
{
    /**
     * Tampilkan daftar seluruh habit.
     */
    public function index()
    {
        // Ambil semua habit (urutkan urutan)
        $habits = Habit::orderBy('urutan')->orderBy('id')->get();

        return Inertia::render('Admin/Habits/Index', [
            'habits' => $habits,
        ]);
    }

    /**
     * Simpan habit baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_habit'        => 'required|string|max:255|unique:habits,nama_habit',
            'deskripsi'         => 'nullable|string',
            'tipe_input'        => 'required|in:boolean,integer',
            'template'          => 'required|string',
            'satuan'            => 'nullable|string|max:50',
            'target_pencapaian' => 'required|integer|min:0',
            'skor_maksimal'     => 'required|integer|min:0',
            'hide_saat_haid'    => 'required|boolean',
            'is_pengganti_haid' => 'required|boolean',
            'status_aktif'      => 'required|boolean',
        ]);

        Habit::create($validated);

        return redirect()->back()->with('success', 'Habit baru berhasil ditambahkan.');
    }

    /**
     * Update data habit.
     */
    public function update(Request $request, Habit $habit)
    {
        $validated = $request->validate([
            'nama_habit'        => 'required|string|max:255|unique:habits,nama_habit,' . $habit->id,
            'deskripsi'         => 'nullable|string',
            'satuan'            => 'nullable|string|max:50',
            'target_pencapaian' => 'required|integer|min:0',
            'skor_maksimal'     => 'required|integer|min:0',
            'status_aktif'      => 'required|boolean',
        ]);

        $habit->update($validated);

        return redirect()->back()->with('success', 'Habit berhasil diperbarui.');
    }

    /**
     * Update urutan habit.
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'ordered_ids' => 'required|array',
            'ordered_ids.*' => 'integer|exists:habits,id',
        ]);

        foreach ($request->ordered_ids as $index => $id) {
            Habit::where('id', $id)->update(['urutan' => $index + 1]);
        }

        return redirect()->back()->with('success', 'Urutan habit berhasil diperbarui.');
    }
}
