<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class EnsureValidApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $hashedToken = hash('sha256', $token);
        $user = User::where('api_token', $hashedToken)
            ->orWhere('api_token', $token)
            ->first();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Auto-upgrade legacy token if it was plaintext
        if ($user->api_token === $token) {
            $user->api_token = $hashedToken;
            $user->save();
        }

        // Log the user in for this request
        Auth::login($user);

        return $next($request);
    }
}
