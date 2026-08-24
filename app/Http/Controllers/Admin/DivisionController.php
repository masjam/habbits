<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DivisionController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        
        $divisions = Division::when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Divisions/Index', [
            'divisions' => $divisions,
            'filters' => [
                'search' => $search
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:divisions,name',
            'target_divisi' => 'nullable|integer|min:0|max:100'
        ]);

        Division::create($validated);

        return redirect()->back()->with('success', 'Divisi berhasil ditambahkan.');
    }

    public function update(Request $request, Division $division)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:divisions,name,' . $division->id,
            'target_divisi' => 'nullable|integer|min:0|max:100'
        ]);

        $division->update($validated);

        return redirect()->back()->with('success', 'Divisi berhasil diperbarui.');
    }

    public function destroy(Division $division)
    {
        $division->delete();

        return redirect()->back()->with('success', 'Divisi berhasil dihapus.');
    }
}
