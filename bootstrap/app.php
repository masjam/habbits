<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Middleware\HandleCors;

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && !getenv('OPENSSL_CONF')) {
    $cnfCandidates = [
        'H:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/extras/ssl/openssl.cnf',
        'C:/xampp/php/extras/ssl/openssl.cnf',
    ];
    foreach ($cnfCandidates as $candidate) {
        if (file_exists($candidate)) {
            putenv("OPENSSL_CONF={$candidate}");
            break;
        }
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = env('TRUSTED_PROXIES');
        if ($trustedProxies) {
            $middleware->trustProxies(at: array_map('trim', explode(',', $trustedProxies)));
        } else {
            $middleware->trustProxies(at: ['127.0.0.1', '::1']);
        }
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
        
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\CheckMaintenanceMode::class,
            \App\Http\Middleware\EnsurePasswordIsChanged::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

         $middleware->alias([
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'api.token'          => \App\Http\Middleware\EnsureValidApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        \Sentry\Laravel\Integration::handles($exceptions);

        // Tangani UnauthorizedException dari Spatie secara bersahabat (hindari popup 403 mentah di Inertia)
        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Anda tidak memiliki hak akses untuk tindakan ini.',
                    'status' => 403,
                ], 403);
            }

            if ($request->user()) {
                $simulatedRole = method_exists($request->user(), 'getSimulatedRole') ? $request->user()->getSimulatedRole() : null;
                $message = $simulatedRole
                    ? 'Akses Dibatasi: Anda sedang dalam simulasi peran "' . ucfirst($simulatedRole) . '". Halaman tersebut khusus Admin / Super Admin.'
                    : 'Akses Dibatasi: Anda tidak memiliki hak akses untuk halaman tersebut.';

                return redirect()->route('dashboard')->with('error', $message);
            }
        });

        // Tangani abort(403) atau HttpException 403 saat simulasi peran aktif
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($e->getStatusCode() === 403 && $request->user() && !($request->is('api/*') || $request->expectsJson())) {
                $simulatedRole = method_exists($request->user(), 'getSimulatedRole') ? $request->user()->getSimulatedRole() : null;
                if ($simulatedRole) {
                    return redirect()->route('dashboard')->with('error', 'Akses Dibatasi: Anda sedang dalam simulasi peran "' . ucfirst($simulatedRole) . '". Halaman tersebut memerlukan peran yang lebih tinggi.');
                }
            }
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            // Validasi 422 tetap memakai handler form bawaan
            if ($status === 422 || $status < 400) {
                return $response;
            }

            $retryAfterHeader = $response->headers->get('Retry-After');
            $retryAfter = is_numeric($retryAfterHeader) ? (int) $retryAfterHeader : null;

            // Jalur API / JSON selalu mengembalikan JSON terstruktur
            if ($request->is('api/*') || $request->expectsJson()) {
                if ($status === 500 && !config('app.debug')) {
                    return response()->json([
                        'message' => 'Terjadi kesalahan pada server.',
                        'status' => 500,
                    ], 500);
                }

                if ($status === 429) {
                    $headers = $retryAfter !== null ? ['Retry-After' => (string) $retryAfter] : [];
                    return response()->json([
                        'message' => \App\Support\ErrorPageData::forStatus(429, $retryAfter)['message'],
                        'status' => 429,
                        'retry_after' => $retryAfter,
                    ], 429, $headers);
                }

                $contentType = (string) $response->headers->get('Content-Type', '');
                if (str_contains($contentType, 'application/json')) {
                    $decoded = json_decode((string) $response->getContent(), true);
                    if (is_array($decoded)) {
                        $decoded['status'] = $status;
                        if (!config('app.debug')) {
                            unset($decoded['exception'], $decoded['file'], $decoded['line'], $decoded['trace']);
                        }
                        return response()->json($decoded, $status);
                    }
                }

                return response()->json([
                    'message' => \App\Support\ErrorPageData::forStatus($status, $retryAfter)['message'],
                    'status' => $status,
                ], $status);
            }

            // 401 Web: redirect ke login dengan intended URL
            if ($status === 401) {
                return redirect()->guest(route('login'))
                    ->with('error', 'Sesi Anda telah berakhir. Silakan masuk kembali.');
            }

            // 419 Inertia: redirect kembali + flash error tanpa halaman buntu
            if ($status === 419) {
                if ($request->header('X-Inertia')) {
                    return back()->with('error', 'Sesi halaman berakhir, silakan coba lagi.');
                }

                return response()->view('errors.419', \App\Support\ErrorPageData::buildPayload(419, $request, safeMode: true), 419);
            }

            // 429 pada login POST: pertahankan UX error form login
            if ($status === 429 && $request->is('login') && $request->isMethod('POST')) {
                return back()->withErrors([
                    'email' => \App\Support\ErrorPageData::forStatus(429, $retryAfter)['message'],
                ])->onlyInput('email');
            }

            // 503 Maintenance / Layanan tidak tersedia (mandiri tanpa DB/session)
            if ($status === 503) {
                return response()->view('errors.503', \App\Support\ErrorPageData::buildPayload(503, $request, safeMode: true), 503);
            }

            // 500+ Server Error
            if ($status >= 500) {
                if (config('app.debug') && !$request->header('X-Inertia')) {
                    return $response;
                }

                $reference = null;
                try {
                    if (class_exists(\Sentry\SentrySdk::class)) {
                        $eventId = \Sentry\SentrySdk::getCurrentHub()->getLastEventId();
                        $reference = $eventId ? (string) $eventId : null;
                    }
                } catch (\Throwable) {}

                if (!$reference) {
                    $reference = (string) \Illuminate\Support\Str::uuid();
                    try {
                        \Illuminate\Support\Facades\Log::error('Unhandled server error', [
                            'reference' => $reference,
                            'url' => $request->fullUrl(),
                            'exception' => $e->getMessage(),
                        ]);
                    } catch (\Throwable) {}
                }

                return response()->view(
                    'errors.500',
                    \App\Support\ErrorPageData::buildPayload(500, $request, $reference, safeMode: true),
                    500
                );
            }

            // 4xx terkelola (400, 403, 404, 405, 408, 413, 429): render Inertia Error.vue dengan fallback Blade
            if (in_array($status, [400, 403, 404, 405, 408, 413, 429], true)) {
                $payload = \App\Support\ErrorPageData::buildPayload($status, $request, null, $retryAfter);
                try {
                    return \Inertia\Inertia::render('Error', $payload)
                        ->toResponse($request)
                        ->setStatusCode($status);
                } catch (\Throwable) {
                    $viewName = view()->exists("errors.{$status}") ? "errors.{$status}" : 'errors.minimal';
                    return response()->view($viewName, $payload, $status);
                }
            }

            return $response;
        });
    })->create();
