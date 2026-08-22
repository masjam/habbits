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
        ]);

        $request->user()->fill($validated);
        $request->user()->save();

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
