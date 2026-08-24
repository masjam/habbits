<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\UserTemplateExport;
use App\Imports\UserImport;

class UserController extends Controller
{
    /**
     * Tampilkan daftar seluruh pengguna.
     */
    public function index(Request $request)
    {
        $currentUser = Auth::user();
        $search = $request->input('search');
        $perPage = $request->input('per_page', 10);

        // Ambil data user, admin hanya bisa melihat role user. Superadmin bisa lihat semua.
        // Tapi sementara kita tampilkan semua saja, atau jika admin, tampilkan pegawai saja.
        $query = User::with(['roles', 'badges'])->orderBy('created_at', 'desc');

        if (!$currentUser->hasRole('superadmin')) {
            // Admin biasa hanya melihat pegawai (role: user)
            $query->whereHas('roles', function ($q) {
                $q->where('name', 'user');
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate($perPage)->withQueryString();

        $users->getCollection()->transform(function ($user) {
            return [
                'id'               => $user->id,
                'name'             => $user->name,
                'email'            => $user->email,
                'gender'           => $user->gender,
                'roles'            => $user->getRoleNames(),
                'nip'              => $user->nip,
                'divisi'           => $user->divisi,
                'status_kehadiran' => $user->status_kehadiran,
                'catatan_pimpinan' => $user->catatan_pimpinan,
                'created_at'       => $user->created_at->format('Y-m-d H:i:s'),
                'badges'           => $user->badges,
            ];
        });

        $badges = \App\Models\Badge::all();
        $divisions = \App\Models\Division::orderBy('name')->get();

        return Inertia::render('Admin/Users/Index', [
            'users'       => $users,
            'allBadges'   => $badges,
            'divisions'   => $divisions,
            'filters'     => ['search' => $search, 'per_page' => (int) $perPage],
            'isSuperadmin'=> $currentUser->hasRole('superadmin'),
        ]);
    }

    /**
     * Simpan user baru.
     */
    public function store(Request $request)
    {
        $currentUser = Auth::user();
        $isSuperadmin = $currentUser->hasRole('superadmin');

        $rules = [
            'name'             => 'required|string|max:255',
            'email'            => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password'         => ['required', Rules\Password::defaults()],
            'gender'           => 'required|in:L,P',
            'nip'              => 'nullable|string|max:255',
            'divisi'           => 'nullable|string|max:255',
            'status_kehadiran' => 'nullable|string|in:Aktif,Cuti,Sakit,Dinas Luar',
        ];

        // Jika superadmin, validasi pilihan role
        if ($isSuperadmin) {
            $rules['role'] = 'required|in:user,admin,superadmin';
        }

        $validated = $request->validate($rules);

        $user = User::create([
            'name'             => $validated['name'],
            'email'            => $validated['email'],
            'password'         => Hash::make($validated['password']),
            'gender'           => $validated['gender'],
            'nip'              => $validated['nip'] ?? null,
            'divisi'           => $validated['divisi'] ?? null,
            'status_kehadiran' => $validated['status_kehadiran'] ?? 'Aktif',
        ]);

        // Tetapkan role
        if ($isSuperadmin && isset($validated['role'])) {
            $user->assignRole($validated['role']);
        } else {
            // Admin biasa hanya bisa membuat pegawai
            $user->assignRole('user');
        }

        return redirect()->back()->with('success', 'Pengguna baru berhasil ditambahkan.');
    }

    /**
     * Update data user.
     */
    public function update(Request $request, User $user)
    {
        $currentUser = Auth::user();
        $isSuperadmin = $currentUser->hasRole('superadmin');

        // Keamanan: Admin biasa hanya boleh edit role user. Superadmin bebas.
        if (!$isSuperadmin && !$user->hasRole('user')) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengedit pengguna ini.');
        }

        $rules = [
            'name'               => 'required|string|max:255',
            'email'              => 'required|string|lowercase|email|max:255|unique:'.User::class.',email,'.$user->id,
            'gender'             => 'required|in:L,P',
            'nip'                => 'nullable|string|max:255',
            'divisi'             => 'nullable|string|max:255',
            'status_kehadiran'   => 'nullable|string|in:Aktif,Cuti,Sakit,Dinas Luar',
            'target_tidak_aktif' => 'nullable|integer|min:0|max:100',
        ];

        // Jika form mengirim password (opsional di edit)
        if ($request->filled('password')) {
            $rules['password'] = ['required', Rules\Password::defaults()];
        }

        if ($isSuperadmin) {
            $rules['role'] = 'required|in:user,admin,superadmin';
        }

        // Admin & Superadmin sama-sama bisa isi catatan jika fitur Notes aktif
        $featureNotes = \App\Models\Setting::where('key', 'feature_notes')->value('value');
        $notesEnabled = $featureNotes === '1' || $featureNotes === 'true';
        if ($notesEnabled) {
            $rules['catatan_pimpinan'] = 'nullable|string';
        }

        $validated = $request->validate($rules);

        $updateData = [
            'name'               => $validated['name'],
            'email'              => $validated['email'],
            'gender'             => $validated['gender'],
            'nip'                => $validated['nip'] ?? null,
            'divisi'             => $validated['divisi'] ?? null,
            'status_kehadiran'   => $validated['status_kehadiran'] ?? 'Aktif',
            'target_tidak_aktif' => $validated['target_tidak_aktif'] ?? null,
        ];

        if (($isSuperadmin || $notesEnabled) && isset($validated['catatan_pimpinan'])) {
            $updateData['catatan_pimpinan'] = $validated['catatan_pimpinan'];
        }

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        if ($isSuperadmin && isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        return redirect()->back()->with('success', 'Profil pengguna berhasil diperbarui.');
    }

    /**
     * Reset password user (Oleh Admin/Superadmin).
     */
    public function resetPassword(Request $request, User $user)
    {
        $currentUser = Auth::user();

        // Keamanan: Admin biasa hanya boleh reset password role user. Superadmin bebas.
        if (!$currentUser->hasRole('superadmin')) {
            if (!$user->hasRole('user')) {
                abort(403, 'Anda tidak memiliki hak akses untuk mereset password pengguna ini.');
            }
        }

        $request->validate([
            'password' => ['required', Rules\Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->back()->with('success', "Password untuk {$user->name} berhasil di-reset.");
    }

    /**
     * Download template Excel untuk import massal.
     */
    public function downloadTemplate()
    {
        return Excel::download(new UserTemplateExport, 'Template_Pegawai_SDAM.xlsx');
    }

    /**
     * Import user massal dari file Excel.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240', // max 10MB
        ]);

        try {
            Excel::import(new UserImport(Auth::user()), $request->file('file'));
            return redirect()->back()->with('success', 'Berhasil mengimpor data pegawai dari file Excel.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['file' => 'Gagal mengimpor file: ' . $e->getMessage()]);
        }
    }

    /**
     * Hapus (soft delete) user.
     */
    public function destroy(User $user)
    {
        $currentUser = Auth::user();

        // Keamanan: Admin biasa hanya boleh menghapus role user. Superadmin bebas.
        if (!$currentUser->hasRole('superadmin') && !$user->hasRole('user')) {
            abort(403, 'Anda tidak memiliki hak akses untuk menghapus pengguna ini.');
        }

        if ($user->id === $currentUser->id) {
            return redirect()->back()->withErrors(['delete' => 'Anda tidak dapat menghapus akun Anda sendiri.']);
        }

        $user->delete();

        return redirect()->back()->with('success', "Pengguna {$user->name} berhasil dihapus.");
    }

    /**
     * Berikan lencana (badge) manual kepada user.
     */
    public function assignBadge(Request $request, User $user)
    {
        $currentUser = Auth::user();
        if (!$currentUser->hasRole('superadmin') && !$currentUser->hasRole('admin')) {
            abort(403, 'Anda tidak memiliki hak akses.');
        }

        $request->validate([
            'badge_id' => 'required|exists:badges,id',
        ]);

        if (!$user->badges()->where('badge_id', $request->badge_id)->exists()) {
            $user->badges()->attach($request->badge_id, ['unlocked_at' => now()]);
            return redirect()->back()->with('success', 'Lencana berhasil diberikan kepada pegawai.');
        }

        return redirect()->back()->withErrors(['badge' => 'Pegawai sudah memiliki lencana ini.']);
    }

    /**
     * Tarik kembali lencana (badge) dari user.
     */
    public function removeBadge(Request $request, User $user)
    {
        $currentUser = Auth::user();
        if (!$currentUser->hasRole('superadmin') && !$currentUser->hasRole('admin')) {
            abort(403, 'Anda tidak memiliki hak akses.');
        }

        $request->validate([
            'badge_id' => 'required|exists:badges,id',
        ]);

        $user->badges()->detach($request->badge_id);
        return redirect()->back()->with('success', 'Lencana berhasil ditarik dari pegawai.');
    }
}
