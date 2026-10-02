<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeacherJournal;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeacherJournalController extends Controller
{
    public function index(Request $request)
    {
        $query = TeacherJournal::with('user')->orderBy('date', 'desc');

        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }
        
        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            })->orWhere('class_name', 'like', "%{$search}%")
              ->orWhere('subject', 'like', "%{$search}%");
        }

        $journals = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/TeacherJournal/Index', [
            'journals' => $journals,
            'filters' => $request->only(['start_date', 'end_date', 'search'])
        ]);
    }
}
