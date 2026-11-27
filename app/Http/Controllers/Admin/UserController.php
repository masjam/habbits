<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
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
        $isSuperadmin = $currentUser->hasRole('superadmin');
        $search = $request->input('search');
        $perPage = max(1, (int) $request->input('per_page', 10));
        $allowedCategories = $isSuperadmin ? ['guru', 'karyawan', 'non_aktif'] : ['guru', 'karyawan'];
        $category = in_array($request->input('category'), $allowedCategories, true)
            ? $request->input('category')
            : 'guru';

        // Ambil data user, admin hanya bisa melihat role user. Superadmin bisa lihat semua.
        // Tapi sementara kita tampilkan semua saja, atau jika admin, tampilkan pegawai saja.
        $query = User::with(['roles', 'badges'])
            ->withExists(['teacherJournals', 'teacherAdministrations'])
            ->orderBy('created_at', 'desc');

        // Akun dengan role 'superadmin' hanya bisa dilihat oleh dirinya sendiri, apapun peran gandanya
        $query->where(function ($q) use ($currentUser) {
            $q->whereDoesntHave('roles', function ($r) {
                $r->where('name', 'superadmin');
            })->orWhere('users.id', $currentUser->id);
        });

        if (!$isSuperadmin) {
            // Admin biasa hanya melihat pegawai aktif/cuti/sakit/dinas luar (Non Aktif disembunyikan)
            $query->whereHas('roles', function ($q) {
                $q->whereIn('name', ['user', 'kurikulum', 'koperasi']);
            })->where(function ($q) {
                $q->whereNull('status_kehadiran')
                  ->orWhere('status_kehadiran', '!=', 'Non Aktif');
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $allMatching = $query->get();

        $guruCount = 0;
        $tendikCount = 0;
        $nonAktifCount = 0;
        $filtered = $allMatching->filter(function (User $user) use ($category, &$guruCount, &$tendikCount, &$nonAktifCount) {
            if ($user->status_kehadiran === 'Non Aktif') {
                $nonAktifCount++;
                return $category === 'non_aktif';
            }

            $resolved = $user->evaluationCategory();
            if ($resolved === 'guru') {
                $guruCount++;
            } else {
                $tendikCount++;
            }
            return $resolved === $category;
        })->values();

        $page = Paginator::resolveCurrentPage();
        $pageItems = $filtered->forPage($page, $perPage)->values()->map(function ($user) {
            return [
                'id'               => $user->id,
                'name'             => $user->name,
                'email'            => $user->email,
                'gender'           => $user->gender,
                'roles'            => $user->getRoleNames(),
                'nip'                 => $user->nip,
                'divisi'              => $user->divisi,
                'evaluation_category' => $user->evaluation_category,
                'resolved_category'   => $user->evaluationCategory(),
                'status_kehadiran'    => $user->status_kehadiran,
                'catatan_pimpinan' => $user->catatan_pimpinan,
                'can_multi_login'  => (bool) $user->can_multi_login,
                'work_start'       => $user->work_start ? substr($user->work_start, 0, 5) : null,
                'work_end'         => $user->work_end ? substr($user->work_end, 0, 5) : null,
                'late_tolerance'   => $user->late_tolerance,
                'rfid_uid'         => $user->rfid_uid,
                'fingerprint_id'   => $user->fingerprint_id,
                'created_at'       => $user->created_at->format('Y-m-d H:i:s'),
                'badges'           => $user->badges,
            ];
        });

        $users = (new LengthAwarePaginator($pageItems, $filtered->count(), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
        ]))->withQueryString();

        $badges = \App\Models\Badge::all();
        $divisions = \App\Models\Division::orderBy('name')->get();

        return Inertia::render('Admin/Users/Index', [
            'users'          => $users,
            'allBadges'      => $badges,
            'divisions'      => $divisions,
            'filters'        => ['category' => $category, 'search' => $search, 'per_page' => $perPage],
            'categoryCounts' => ['guru' => $guruCount, 'karyawan' => $tendikCount, 'non_aktif' => $nonAktifCount],
            'isSuperadmin'   => $isSuperadmin,
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
            'nip'                 => 'nullable|string|max:255',
            'divisi'              => 'nullable|string|max:255',
            'evaluation_category' => 'nullable|in:guru,karyawan',
            'status_kehadiran'    => 'nullable|string|in:Aktif,Cuti,Sakit,Dinas Luar,Non Aktif',
            'work_start'       => 'nullable|string|max:10',
            'work_end'         => 'nullable|string|max:10',
            'late_tolerance'   => 'nullable|integer|min:0|max:120',
            'rfid_uid'         => 'nullable|string|max:255|unique:'.User::class,
            'fingerprint_id'   => 'nullable|string|max:255|unique:'.User::class,
        ];

        if ($isSuperadmin) {
            $rules['role'] = 'required|in:user,admin,superadmin,kurikulum,koperasi';
            $rules['can_multi_login'] = 'nullable|boolean';
            $rules['is_tata_usaha'] = 'nullable|boolean';
            $rules['is_kepala_sekolah'] = 'nullable|boolean';
            $rules['is_kurikulum'] = 'nullable|boolean';
            $rules['is_koperasi'] = 'nullable|boolean';
        }

        $validated = $request->validate($rules);

        $user = User::create([
            'name'             => $validated['name'],
            'email'            => $validated['email'],
            'password'         => Hash::make($validated['password']),
            'gender'           => $validated['gender'],
            'nip'                 => $validated['nip'] ?? null,
            'divisi'              => $validated['divisi'] ?? null,
            'evaluation_category' => $validated['evaluation_category'] ?? null,
            'status_kehadiran'    => $validated['status_kehadiran'] ?? 'Aktif',
            'can_multi_login'  => $isSuperadmin ? ($validated['can_multi_login'] ?? false) : false,
            'work_start'       => !empty($validated['work_start']) ? $validated['work_start'] : null,
            'work_end'         => !empty($validated['work_end']) ? $validated['work_end'] : null,
            'late_tolerance'   => isset($validated['late_tolerance']) && $validated['late_tolerance'] !== '' ? $validated['late_tolerance'] : null,
        ]);

        // Tetapkan role
        $rolesToAssign = [];
        if ($isSuperadmin && isset($validated['role'])) {
            $rolesToAssign[] = $validated['role'];
        } else {
            $rolesToAssign[] = 'user';
        }
        
        if ($isSuperadmin && !empty($validated['is_tata_usaha'])) {
            $rolesToAssign[] = 'tata_usaha';
        }

        if ($isSuperadmin && !empty($validated['is_kepala_sekolah'])) {
            $rolesToAssign[] = 'kepala_sekolah';
        }

        if ($isSuperadmin && !empty($validated['is_kurikulum'])) {
            $rolesToAssign[] = 'kurikulum';
        }

        if ($isSuperadmin && !empty($validated['is_koperasi'])) {
            $rolesToAssign[] = 'koperasi';
        }

        $rolesToAssign = array_values(array_unique($rolesToAssign));
        $user->syncRoles($rolesToAssign);

        return redirect()->route('admin.users.index')
            ->with('success', "Pengguna {$user->name} berhasil ditambahkan.");
    }

    /**
     * Akun dengan peran istimewa (kepala sekolah / tata usaha) tidak boleh
     * diambil alih oleh admin biasa: ganti password dan hapus hanya untuk superadmin.
     */
    private function isPrivilegedTarget(User $target): bool
    {
        return $target->hasAnyRole(['kepala_sekolah', 'tata_usaha', 'admin', 'superadmin'])
            || $target->status_kehadiran === 'Non Aktif';
    }

    /**
     * Update user yang ada.
     */
    public function update(Request $request, User $user)
    {
        $currentUser = Auth::user();
        $isSuperadmin = $currentUser->hasRole('superadmin');

        // Keamanan: Admin biasa hanya boleh edit role user / kurikulum / koperasi (dan bukan Non Aktif). Superadmin bebas.
        if (!$isSuperadmin && (!$user->hasAnyRole(['user', 'kurikulum', 'koperasi']) || $this->isPrivilegedTarget($user))) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengedit pengguna ini.');
        }

        $rules = [
            'name'               => 'required|string|max:255',
            'email'              => 'required|string|lowercase|email|max:255|unique:'.User::class.',email,'.$user->id,
            'gender'             => 'required|in:L,P',
            'nip'                 => 'nullable|string|max:255',
            'divisi'              => 'nullable|string|max:255',
            'evaluation_category' => 'nullable|in:guru,karyawan',
            'status_kehadiran'    => 'nullable|string|in:Aktif,Cuti,Sakit,Dinas Luar,Non Aktif',
            'target_tidak_aktif' => 'nullable|integer|min:0|max:100',
            'work_start'         => 'nullable|string|max:10',
            'work_end'           => 'nullable|string|max:10',
            'late_tolerance'     => 'nullable|integer|min:0|max:120',
            'rfid_uid'           => 'nullable|string|max:255|unique:'.User::class.',rfid_uid,'.$user->id,
            'fingerprint_id'     => 'nullable|string|max:255|unique:'.User::class.',fingerprint_id,'.$user->id,
        ];

        // Admin biasa tidak boleh mengganti password akun istimewa
        if ($request->filled('password') && !$isSuperadmin && $this->isPrivilegedTarget($user)) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengganti password pengguna ini.');
        }

        // Jika form mengirim password (opsional di edit)
        if ($request->filled('password')) {
            $rules['password'] = ['required', Rules\Password::defaults()];
        }

        if ($isSuperadmin) {
            $rules['role'] = 'required|in:user,admin,superadmin,kurikulum,koperasi';
            $rules['can_multi_login'] = 'nullable|boolean';
            $rules['is_tata_usaha'] = 'nullable|boolean';
            $rules['is_kepala_sekolah'] = 'nullable|boolean';
            $rules['is_kurikulum'] = 'nullable|boolean';
            $rules['is_koperasi'] = 'nullable|boolean';
        }

        // Admin & Superadmin sama-sama bisa isi catatan jika fitur Notes aktif
        $featureNotes = \App\Models\Setting::get('feature_notes');
        $notesEnabled = $featureNotes === '1' || $featureNotes === 'true';
        if ($notesEnabled) {
            $rules['catatan_pimpinan'] = 'nullable|string';
        }

        $validated = $request->validate($rules);

        $updateData = [
            'name'               => $validated['name'],
            'email'              => $validated['email'],
            'gender'             => $validated['gender'],
            'nip'                 => $validated['nip'] ?? null,
            'divisi'              => $validated['divisi'] ?? null,
            'evaluation_category' => $validated['evaluation_category'] ?? null,
            'status_kehadiran'   => $validated['status_kehadiran'] ?? 'Aktif',
            'target_tidak_aktif' => $validated['target_tidak_aktif'] ?? null,
            'work_start'         => !empty($validated['work_start']) ? $validated['work_start'] : null,
            'work_end'           => !empty($validated['work_end']) ? $validated['work_end'] : null,
            'late_tolerance'     => isset($validated['late_tolerance']) && $validated['late_tolerance'] !== '' ? $validated['late_tolerance'] : null,
            'rfid_uid'           => $validated['rfid_uid'] ?? null,
            'fingerprint_id'     => $validated['fingerprint_id'] ?? null,
        ];

        if ($isSuperadmin && array_key_exists('can_multi_login', $validated)) {
            $updateData['can_multi_login'] = $validated['can_multi_login'];
        }

        if (($isSuperadmin || $notesEnabled) && isset($validated['catatan_pimpinan'])) {
            $updateData['catatan_pimpinan'] = $validated['catatan_pimpinan'];
        }

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        if ($isSuperadmin && isset($validated['role'])) {
            $rolesToAssign = [$validated['role']];
            if (!empty($validated['is_tata_usaha'])) {
                $rolesToAssign[] = 'tata_usaha';
            }
            if (!empty($validated['is_kepala_sekolah'])) {
                $rolesToAssign[] = 'kepala_sekolah';
            }
            if (!empty($validated['is_kurikulum'])) {
                $rolesToAssign[] = 'kurikulum';
            }
            if (!empty($validated['is_koperasi'])) {
                $rolesToAssign[] = 'koperasi';
            }
            $rolesToAssign = array_values(array_unique($rolesToAssign));
            $user->syncRoles($rolesToAssign);
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
            if (!$user->hasRole('user') || $this->isPrivilegedTarget($user)) {
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
        if (!$currentUser->hasRole('superadmin') && (!$user->hasRole('user') || $this->isPrivilegedTarget($user))) {
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

    /**
     * Ubah kategori evaluasi (guru / karyawan / otomatis) secara massal.
     */
    public function bulkUpdateCategory(Request $request)
    {
        $currentUser = Auth::user();
        $isSuperadmin = $currentUser->hasRole('superadmin');

        $validated = $request->validate([
            'user_ids'            => 'required|array|min:1',
            'user_ids.*'          => 'integer|exists:users,id',
            'evaluation_category' => 'nullable|in:guru,karyawan',
        ]);

        $targets = User::with('roles')->whereIn('id', $validated['user_ids'])->get();

        if (!$isSuperadmin) {
            foreach ($targets as $target) {
                if (!$target->hasAnyRole(['user', 'kurikulum', 'koperasi']) || $this->isPrivilegedTarget($target)) {
                    abort(403, 'Anda tidak memiliki hak akses untuk mengubah kategori akun Admin, Superadmin, Kepala Sekolah, atau Tata Usaha.');
                }
            }
        }

        $category = $validated['evaluation_category'] ?? null;
        User::whereIn('id', $targets->pluck('id'))->update(['evaluation_category' => $category]);

        $label = match ($category) {
            'guru'     => 'Guru',
            'karyawan' => 'Tendik (Karyawan)',
            default    => 'Deteksi Otomatis',
        };

        return redirect()->back()->with('success', "Kategori {$targets->count()} pegawai berhasil diubah menjadi {$label}.");
    }
}
