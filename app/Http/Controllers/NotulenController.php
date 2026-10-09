<?php

namespace App\Http\Controllers;

use App\Models\Notulen;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class NotulenController extends Controller
{
    public function index(Request $request)
    {
        $query = Notulen::query();
        
        if ($request->has('search') && $request->search != '') {
            $query->where('judul_rapat', 'like', '%' . $request->search . '%')
                  ->orWhere('jenis_rapat', 'like', '%' . $request->search . '%');
        }

        $notulens = $query->orderBy('tanggal_waktu', 'desc')->paginate(10)->withQueryString();

        return Inertia::render('Notulen/Index', [
            'notulens' => $notulens,
            'filters' => $request->only(['search'])
        ]);
    }

    public function create()
    {
        $users = User::orderBy('name')->get(['id', 'name']);
        
        return Inertia::render('Notulen/Create', [
            'users' => $users
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'judul_rapat' => 'required|string|max:255',
            'jenis_rapat' => 'required|string|max:255',
            'tanggal_waktu' => 'required|date',
            'pimpinan_rapat' => 'required|string|max:255',
            'lokasi' => 'required|string|max:255',
            'peserta_rapat' => 'nullable|string',
            'isi_pembahasan' => 'required|string',
            'tindak_lanjut' => 'nullable|string',
            'dokumentasi.*' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048'
        ]);

        $validated['daftar_hadir'] = null; // Mulai dengan daftar hadir kosong

        $dokPaths = [];
        if ($request->hasFile('dokumentasi')) {
            foreach ($request->file('dokumentasi') as $file) {
                $path = $file->store('notulen', 'public');
                $dokPaths[] = $path;
            }
        }
        $validated['dokumentasi'] = count($dokPaths) > 0 ? $dokPaths : null;

        Notulen::create($validated);

        return redirect()->route('notulen.index')->with('success', 'Notulen berhasil ditambahkan.');
    }

    public function destroy(Notulen $notulen)
    {
        if ($notulen->dokumentasi) {
            foreach ($notulen->dokumentasi as $path) {
                Storage::disk('public')->delete($path);
            }
        }
        
        $notulen->delete();
        
        return redirect()->back()->with('success', 'Notulen berhasil dihapus.');
    }

    public function markHadir(Notulen $notulen)
    {
        $userName = auth()->user()->name;
        
        $hadirList = $notulen->daftar_hadir ? explode(', ', $notulen->daftar_hadir) : [];
        
        if (!in_array($userName, $hadirList)) {
            $hadirList[] = $userName;
            $notulen->update([
                'daftar_hadir' => implode(', ', $hadirList)
            ]);
            return redirect()->back()->with('success', 'Anda berhasil menandai kehadiran pada rapat ini.');
        }

        return redirect()->back()->with('info', 'Anda sudah tercatat hadir.');
    }
}
