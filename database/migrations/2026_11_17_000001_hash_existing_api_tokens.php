<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $users = DB::table('users')
            ->whereNotNull('api_token')
            ->where('api_token', '!=', '')
            ->get(['id', 'api_token']);

        foreach ($users as $user) {
            // If token is not already a 64-char hex string (SHA-256), hash it
            if (strlen($user->api_token) !== 64 || !ctype_xdigit($user->api_token)) {
                DB::table('users')->where('id', $user->id)->update([
                    'api_token' => hash('sha256', $user->api_token),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // One-way cryptographic hash cannot be reversed.
    }
};
