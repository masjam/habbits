<?php

namespace App\Http\Controllers;

use App\Models\Notulen;
use App\Models\User;
use App\Jobs\SendNotulenApprovalNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NotulenController extends Controller
{
    private function storeBase64Signature($base64String, $prefix = 'signature')
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $type)) {
            $data = substr($base64String, strpos($base64String, ',') + 1);
            $type = strtolower($type[1]);
            $data = base64_decode($data);
            $fileName = 'signatures/' . $prefix . '_' . time() . '_' . Str::random(5) . '.' . $type;
            Storage::disk('public')->put($fileName, $data);
            return $fileName;
        }
        return $base64String;
    }

    public function index(Request $request)
    {
        $query = Notulen::query();
        
        if ($request->has('search') && $request->search != '') {
            $query->where('judul_rapat', 'like', '%' . $request->search . '%')
                  ->orWhere('jenis_rapat', 'like', '%' . $request->search . '%');
        }

        $notulens = $query->with('pesertas')->orderBy('tanggal_waktu', 'desc')->paginate(10)->withQueryString();

        return Inertia::render('Notulen/Index', [
            'notulens' => $notulens,
            'filters' => $request->only(['search'])
        ]);
    }

    public function create()
    {
        $users = User::whereDoesntHave('roles', function ($query) {
            $query->whereIn('name', ['superadmin', 'admin']);
        })->orderBy('name')->get(['id', 'name']);
        
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
            'ttd_notulis' => 'required|string',
            'dokumentasi.*' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048'
        ]);

        $userName = auth()->user()->name;
        $pimpinan = $validated['pimpinan_rapat'];

        // Siapkan array daftar hadir dan peserta rapat
        $pesertaList = $validated['peserta_rapat'] ? explode(', ', $validated['peserta_rapat']) : [];
        $hadirList = [];

        // Masukkan Notulis ke daftar peserta dan hadir (jika belum ada)
        if (!in_array($userName, $pesertaList)) $pesertaList[] = $userName;
        if (!in_array($userName, $hadirList)) $hadirList[] = $userName;

        // Masukkan Pimpinan ke daftar peserta dan hadir (jika belum ada)
        if (!in_array($pimpinan, $pesertaList)) $pesertaList[] = $pimpinan;
        if (!in_array($pimpinan, $hadirList)) $hadirList[] = $pimpinan;

        // Pisahkan user terdaftar vs tamu non-pegawai
        $registeredUsers = User::whereIn('name', $pesertaList)->get(['id', 'name']);
        $registeredNames = $registeredUsers->pluck('name')->toArray();

        $tamuPeserta = array_diff($pesertaList, $registeredNames);
        $tamuHadir = array_diff($hadirList, $registeredNames);

        $validated['peserta_rapat'] = count($tamuPeserta) > 0 ? implode(', ', $tamuPeserta) : null;
        $validated['daftar_hadir'] = count($tamuHadir) > 0 ? implode(', ', $tamuHadir) : null;

        $dokPaths = [];
        if ($request->hasFile('dokumentasi')) {
            foreach ($request->file('dokumentasi') as $file) {
                $path = $file->store('notulen', 'public');
                $dokPaths[] = $path;
            }
        }
        $validated['dokumentasi'] = count($dokPaths) > 0 ? $dokPaths : null;

        if (isset($validated['ttd_notulis']) && str_starts_with($validated['ttd_notulis'], 'data:image')) {
            $validated['ttd_notulis'] = $this->storeBase64Signature($validated['ttd_notulis'], 'notulis');
        }

        $validated['user_id'] = auth()->id();
        
        $notulen = Notulen::create($validated);

        // Simpan ke Pivot Table
        $pivotData = [];
        foreach ($registeredUsers as $user) {
            $pivotData[$user->id] = ['is_hadir' => in_array($user->name, $hadirList)];
        }
        $notulen->pesertas()->sync($pivotData);

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
        $userId = auth()->id();

        // Cek apakah user ada di relasi pivot
        $isPeserta = $notulen->pesertas()->where('user_id', $userId)->exists();
        
        if ($isPeserta) {
            $notulen->pesertas()->updateExistingPivot($userId, ['is_hadir' => true]);
            return redirect()->back()->with('success', 'Anda berhasil menandai kehadiran pada rapat ini.');
        } else {
            // Jika tidak ada di pivot, anggap sebagai legacy / fallback teks
            $hadirList = $notulen->daftar_hadir ? explode(', ', $notulen->daftar_hadir) : [];
            if (!in_array($userName, $hadirList)) {
                $hadirList[] = $userName;
                $notulen->update([
                    'daftar_hadir' => implode(', ', $hadirList)
                ]);
                return redirect()->back()->with('success', 'Anda berhasil menandai kehadiran pada rapat ini.');
            }
        }

        return redirect()->back()->with('info', 'Anda sudah tercatat hadir.');
    }

    public function show(Notulen $notulen)
    {
        $notulen->load(['creator', 'pesertas']);
        return Inertia::render('Notulen/Show', [
            'notulen' => $notulen
        ]);
    }

    public function edit(Notulen $notulen)
    {
        $notulen->load('pesertas');
        if ($notulen->is_approved) {
            return redirect()->route('notulen.index')->with('error', 'Notulen sudah disetujui dan tidak dapat diubah.');
        }

        $users = User::whereDoesntHave('roles', function ($query) {
            $query->whereIn('name', ['superadmin', 'admin']);
        })->orderBy('name')->get(['id', 'name']);
        
        return Inertia::render('Notulen/Edit', [
            'notulen' => $notulen,
            'users' => $users
        ]);
    }

    public function update(Request $request, Notulen $notulen)
    {
        if ($notulen->is_approved) {
            return redirect()->route('notulen.index')->with('error', 'Notulen sudah disetujui dan tidak dapat diubah.');
        }

        $validated = $request->validate([
            'judul_rapat' => 'required|string|max:255',
            'jenis_rapat' => 'required|string|max:255',
            'tanggal_waktu' => 'required|date',
            'pimpinan_rapat' => 'required|string|max:255',
            'lokasi' => 'required|string|max:255',
            'peserta_rapat' => 'nullable|string',
            'isi_pembahasan' => 'required|string',
            'tindak_lanjut' => 'nullable|string',
            'ttd_notulis' => 'required|string',
            'dokumentasi.*' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048'
        ]);

        $userName = auth()->user()->name;
        $pimpinan = $validated['pimpinan_rapat'];

        $pesertaList = $validated['peserta_rapat'] ? explode(', ', $validated['peserta_rapat']) : [];
        $hadirList = $notulen->daftar_hadir ? explode(', ', $notulen->daftar_hadir) : [];

        if (!in_array($userName, $pesertaList)) $pesertaList[] = $userName;
        if (!in_array($userName, $hadirList)) $hadirList[] = $userName;

        if (!in_array($pimpinan, $pesertaList)) $pesertaList[] = $pimpinan;
        if (!in_array($pimpinan, $hadirList)) $hadirList[] = $pimpinan;

        // Pisahkan user terdaftar vs tamu non-pegawai
        $registeredUsers = User::whereIn('name', $pesertaList)->get(['id', 'name']);
        $registeredNames = $registeredUsers->pluck('name')->toArray();

        $tamuPeserta = array_diff($pesertaList, $registeredNames);
        $tamuHadir = array_diff($hadirList, $registeredNames);

        $validated['peserta_rapat'] = count($tamuPeserta) > 0 ? implode(', ', $tamuPeserta) : null;
        $validated['daftar_hadir'] = count($tamuHadir) > 0 ? implode(', ', $tamuHadir) : null;

        $dokPaths = $notulen->dokumentasi ?? [];
        if ($request->hasFile('dokumentasi')) {
            foreach ($request->file('dokumentasi') as $file) {
                $path = $file->store('notulen', 'public');
                $dokPaths[] = $path;
            }
        }
        $validated['dokumentasi'] = count($dokPaths) > 0 ? $dokPaths : null;

        if (isset($validated['ttd_notulis']) && str_starts_with($validated['ttd_notulis'], 'data:image')) {
            $validated['ttd_notulis'] = $this->storeBase64Signature($validated['ttd_notulis'], 'notulis');
        }

        $notulen->update($validated);

        // Update Pivot Table
        $pivotData = [];
        foreach ($registeredUsers as $user) {
            $pivotData[$user->id] = ['is_hadir' => in_array($user->name, $hadirList)];
        }
        $notulen->pesertas()->sync($pivotData);

        return redirect()->route('notulen.index')->with('success', 'Notulen berhasil diperbarui.');
    }

    public function approve(Request $request, Notulen $notulen)
    {
        $request->validate([
            'ttd_pimpinan' => 'required|string'
        ]);

        $ttdPimpinan = $request->ttd_pimpinan;
        if (str_starts_with($ttdPimpinan, 'data:image')) {
            $ttdPimpinan = $this->storeBase64Signature($ttdPimpinan, 'pimpinan');
        }

        $notulen->update([
            'is_approved' => true,
            'ttd_pimpinan' => $ttdPimpinan
        ]);
        
        // Dispatch Job ke Background Worker
        SendNotulenApprovalNotification::dispatch($notulen);
        
        return redirect()->back()->with('success', 'Notulen berhasil disetujui.');
    }
}
