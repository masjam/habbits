<?php

namespace App\Http\Controllers\Corporate;

use App\Http\Controllers\Controller;

use App\Models\Notulen;
use App\Models\User;
use App\Jobs\SendNotulenApprovalNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stevebauman\Purify\Facades\Purify;

class NotulenController extends Controller
{
    private function storeBase64Signature($base64String, $prefix = 'signature', $field = 'ttd')
    {
        return \App\Services\Base64ImageService::store($base64String, 'signatures', $prefix, $field);
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
        $pesertaList = !empty($validated['peserta_rapat']) ? explode(', ', $validated['peserta_rapat']) : [];
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
        
        // Tamu non-pegawai otomatis dianggap hadir
        $tamuHadir = $tamuPeserta;

        $validated['peserta_rapat'] = count($tamuPeserta) > 0 ? implode(', ', $tamuPeserta) : null;
        $validated['daftar_hadir'] = count($tamuHadir) > 0 ? implode(', ', $tamuHadir) : null;

        $dokPaths = [];
        if ($request->hasFile('dokumentasi')) {
            foreach ($request->file('dokumentasi') as $file) {
                $path = $file->store('notulen', 'private');
                $dokPaths[] = $path;
            }
        }
        $validated['dokumentasi'] = count($dokPaths) > 0 ? $dokPaths : null;

        $validated['ttd_notulis'] = $this->storeBase64Signature($validated['ttd_notulis'], 'notulis', 'ttd_notulis');

        $validated['isi_pembahasan'] = Purify::clean($validated['isi_pembahasan']);
        if (!empty($validated['tindak_lanjut'])) {
            $validated['tindak_lanjut'] = Purify::clean($validated['tindak_lanjut']);
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
        Gate::authorize('delete', $notulen);

        if ($notulen->dokumentasi) {
            foreach ($notulen->dokumentasi as $path) {
                if (Storage::disk('private')->exists($path)) {
                    Storage::disk('private')->delete($path);
                } elseif (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        }
        
        $notulen->delete();
        
        return redirect()->back()->with('success', 'Notulen berhasil dihapus.');
    }

    public function markHadir(Notulen $notulen)
    {
        $userName = auth()->user()->name;
        $userId = auth()->id();

        // 1. Cek apakah user ada di relasi pivot pesertas
        $isPesertaPivot = $notulen->pesertas()->where('user_id', $userId)->exists();
        if ($isPesertaPivot) {
            $notulen->pesertas()->updateExistingPivot($userId, ['is_hadir' => true]);
            return redirect()->back()->with('success', 'Anda berhasil menandai kehadiran pada rapat ini.');
        }

        // 2. Jalur fallback data lama: pastikan nama user memang ada di daftar peserta rapat awal
        $pesertaList = $notulen->peserta_rapat ? array_map('trim', explode(',', $notulen->peserta_rapat)) : [];
        if (in_array($userName, $pesertaList)) {
            $hadirList = $notulen->daftar_hadir ? array_map('trim', explode(',', $notulen->daftar_hadir)) : [];
            if (!in_array($userName, $hadirList)) {
                $hadirList[] = $userName;
                $notulen->update([
                    'daftar_hadir' => implode(', ', $hadirList)
                ]);
                return redirect()->back()->with('success', 'Anda berhasil menandai kehadiran pada rapat ini.');
            }
            return redirect()->back()->with('info', 'Anda sudah tercatat hadir.');
        }

        abort(403, 'Anda bukan peserta dalam rapat ini.');
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
        Gate::authorize('update', $notulen);

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
        Gate::authorize('update', $notulen);

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

        $pesertaList = !empty($validated['peserta_rapat']) ? explode(', ', $validated['peserta_rapat']) : [];
        $hadirList = $notulen->daftar_hadir ? explode(', ', $notulen->daftar_hadir) : [];

        if (!in_array($userName, $pesertaList)) $pesertaList[] = $userName;
        if (!in_array($userName, $hadirList)) $hadirList[] = $userName;

        if (!in_array($pimpinan, $pesertaList)) $pesertaList[] = $pimpinan;
        if (!in_array($pimpinan, $hadirList)) $hadirList[] = $pimpinan;

        // Pisahkan user terdaftar vs tamu non-pegawai
        $registeredUsers = User::whereIn('name', $pesertaList)->get(['id', 'name']);
        $registeredNames = $registeredUsers->pluck('name')->toArray();

        $tamuPeserta = array_diff($pesertaList, $registeredNames);
        
        // Tamu non-pegawai otomatis dianggap hadir
        $tamuHadir = $tamuPeserta;

        $validated['peserta_rapat'] = count($tamuPeserta) > 0 ? implode(', ', $tamuPeserta) : null;
        $validated['daftar_hadir'] = count($tamuHadir) > 0 ? implode(', ', $tamuHadir) : null;

        $dokPaths = $notulen->dokumentasi ?? [];
        if ($request->hasFile('dokumentasi')) {
            foreach ($request->file('dokumentasi') as $file) {
                $path = $file->store('notulen', 'private');
                $dokPaths[] = $path;
            }
        }
        $validated['dokumentasi'] = count($dokPaths) > 0 ? $dokPaths : null;

        if (!empty($validated['ttd_notulis']) && str_starts_with($validated['ttd_notulis'], 'data:image')) {
            $validated['ttd_notulis'] = $this->storeBase64Signature($validated['ttd_notulis'], 'notulis', 'ttd_notulis');
        } else {
            unset($validated['ttd_notulis']);
        }

        $validated['isi_pembahasan'] = Purify::clean($validated['isi_pembahasan']);
        if (!empty($validated['tindak_lanjut'])) {
            $validated['tindak_lanjut'] = Purify::clean($validated['tindak_lanjut']);
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
        Gate::authorize('approve', $notulen);

        $request->validate([
            'ttd_pimpinan' => 'required|string'
        ]);

        $ttdPimpinan = $this->storeBase64Signature($request->ttd_pimpinan, 'pimpinan', 'ttd_pimpinan');

        $notulen->update([
            'is_approved' => true,
            'ttd_pimpinan' => $ttdPimpinan
        ]);
        
        // Dispatch Job ke Background Worker
        SendNotulenApprovalNotification::dispatch($notulen);
        
        return redirect()->back()->with('success', 'Notulen berhasil disetujui.');
    }

    /**
     * Unduh atau lihat berkas dokumentasi notulen dengan verifikasi hak akses.
     */
    public function serveDokumentasi(Request $request, Notulen $notulen, string $filename)
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $isAuthorized = $user->hasAnyRole(['admin', 'superadmin', 'kepala_sekolah'])
            || (method_exists($user, 'isActualSuperadmin') && $user->isActualSuperadmin())
            || $notulen->user_id === $user->id
            || $notulen->pesertas()->where('user_id', $user->id)->exists()
            || in_array($user->name, array_map('trim', explode(',', (string) $notulen->peserta_rapat)));

        if (!$isAuthorized) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat dokumentasi rapat ini.');
        }

        if (str_contains($filename, '..')) {
            abort(400, 'Invalid path');
        }

        $fullPath = 'notulen/' . $filename;
        $disk = Storage::disk('private')->exists($fullPath)
            ? 'private'
            : (Storage::disk('public')->exists($fullPath) ? 'public' : null);

        if (!$disk) {
            abort(404, 'Dokumentasi tidak ditemukan.');
        }

        return Storage::disk($disk)->response($fullPath);
    }
}
