<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Habit;
use Illuminate\Http\Request;

class HabitController extends Controller
{
    /**
     * Get all active habits.
     */
    public function index()
    {
        $habits = Habit::where('status_aktif', true)
            ->orderBy('urutan')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar habit berhasil diambil',
            'data' => $habits
        ]);
    }
}
