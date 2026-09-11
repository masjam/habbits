<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Update profil pengguna via API.
     */
    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'min:10', 'max:14'],
            'personal_target' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $user = Auth::user();
        $user->fill([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'personal_target' => $validated['personal_target'] ?? null,
        ]);

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'gender' => $user->gender,
                    'phone' => $user->phone,
                    'personal_target' => $user->personal_target,
                    'avatar' => $user->avatar,
                ]
            ]
        ]);
    }
}
