<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProfileController extends Controller
{
    /**
     * Tampilkan halaman profil pengguna (Ganti Password).
     */
    public function edit(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => false,
            'status' => session('status'),
            'profileStatus' => session('profile_status'),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'personal_target' => $user->personal_target,
                'avatar' => $user->avatar,
                'gender' => $user->gender,
                'nip' => $user->nip,
                'divisi' => $user->divisi,
            ],
        ]);
    }

    /**
     * Hapus file avatar lama secara aman (hanya jika berada di direktori avatars/).
     */
    private function deleteOldAvatarFile(?string $path): void
    {
        if (empty($path)) {
            return;
        }

        if (str_starts_with($path, 'avatars/') && !str_contains($path, '..')) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /**
     * Update profil pengguna.
     */
    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'min:10', 'max:14'],
            'personal_target' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'avatar' => ['nullable'], // can be string (predefined) or file
        ]);

        $user = $request->user();
        $user->fill([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'personal_target' => $validated['personal_target'] ?? null,
        ]);

        if ($request->hasFile('avatar')) {
            $request->validate([
                'avatar' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            ]);
            
            // Delete old avatar if it's a local avatar file
            $this->deleteOldAvatarFile($user->avatar);

            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
        } elseif ($request->filled('avatar') && is_string($validated['avatar'])) {
            $avatarStr = (string) $validated['avatar'];
            $isDicebear = (bool) preg_match('/^https:\/\/api\.dicebear\.com\/[a-zA-Z0-9\.\/\?=&,_\-]+$/', $avatarStr);
            $isLocalPreset = (bool) preg_match('/^\/?img\/[a-zA-Z0-9_\-\/]+\.(png|jpg|jpeg|webp)$/i', $avatarStr);

            if (!$isDicebear && !$isLocalPreset) {
                throw ValidationException::withMessages([
                    'avatar' => ['Avatar bawaan yang dipilih tidak valid atau tidak diizinkan.']
                ]);
            }

            // Predefined avatar selected
            if ($user->avatar && $user->avatar !== $avatarStr) {
                $this->deleteOldAvatarFile($user->avatar);
            }
            $user->avatar = $avatarStr;
        }

        $user->save();

        return back()->with('profile_status', 'profile-updated');
    }

    /**
     * Update password pengguna.
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'password-updated');
    }
}
