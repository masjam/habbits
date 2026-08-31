<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class MultiAccountController extends Controller
{
    /**
     * Start the process of adding a new account.
     * Sets a session flag and redirects to the login page.
     */
    public function addAccount(Request $request)
    {
        // Only allow if current user has the permission, or if it's already a multi-account session where at least one has it.
        $user = Auth::user();
        if (!$user || !$user->can_multi_login) {
            abort(403, 'Anda tidak memiliki hak untuk menggunakan fitur multi-akun.');
        }

        $request->session()->put('is_adding_account', true);
        
        return redirect()->route('login');
    }

    /**
     * Cancel the add account process.
     */
    public function cancelAddAccount(Request $request)
    {
        $request->session()->forget('is_adding_account');
        return redirect()->route('dashboard');
    }

    /**
     * Switch to a different account in the multi_accounts session.
     */
    public function switchAccount(Request $request, $id)
    {
        $accounts = $request->session()->get('multi_accounts', []);
        
        // Ensure the requested ID is in our multi_accounts array
        $found = false;
        foreach ($accounts as $account) {
            if ($account['id'] == $id) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            return redirect()->back()->withErrors(['switch' => 'Akun tidak ditemukan dalam sesi Anda.']);
        }

        // Login using that ID
        Auth::loginUsingId($id);

        return redirect()->route('dashboard')->with('success', 'Berhasil beralih akun.');
    }

    /**
     * Remove an account from the multi_accounts session.
     */
    public function removeAccount(Request $request, $id)
    {
        $accounts = $request->session()->get('multi_accounts', []);
        $newAccounts = array_filter($accounts, function($acc) use ($id) {
            return $acc['id'] != $id;
        });

        // Re-index array
        $newAccounts = array_values($newAccounts);
        $request->session()->put('multi_accounts', $newAccounts);

        // If we removed the currently active account, switch to another one if available
        if (Auth::id() == $id) {
            if (count($newAccounts) > 0) {
                Auth::loginUsingId($newAccounts[0]['id']);
                return redirect()->route('dashboard')->with('success', 'Akun berhasil dihapus dari sesi. Anda telah dialihkan ke akun lain.');
            } else {
                // If no accounts left, logout completely
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect('/');
            }
        }

        return redirect()->back()->with('success', 'Akun berhasil dihapus dari sesi.');
    }
}
