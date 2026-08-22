<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class ProfileController extends Controller
{
    /**
     * Tampilkan halaman profil pengguna (Ganti Password).
     */
    public function edit(Request $request)
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => false,
            'status' => session('status'),
            'profileStatus' => session('profile_status'),
            'user' => $request->user(),
        ]);
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
                'avatar' => ['image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:2048'],
            ]);
            
            // Delete old avatar if it's a local file
            if ($user->avatar && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->avatar)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar);
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
        } elseif ($request->filled('avatar') && is_string($validated['avatar'])) {
            // Predefined avatar selected
            if ($user->avatar && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->avatar) && $user->avatar !== $validated['avatar']) {
                 \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $validated['avatar'];
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
