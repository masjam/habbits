<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
        
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\CheckMaintenanceMode::class,

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
            if ($e->getStatusCode() === 403 && $request->user()) {
                $simulatedRole = method_exists($request->user(), 'getSimulatedRole') ? $request->user()->getSimulatedRole() : null;
                if ($simulatedRole) {
                    return redirect()->route('dashboard')->with('error', 'Akses Dibatasi: Anda sedang dalam simulasi peran "' . ucfirst($simulatedRole) . '". Halaman tersebut memerlukan peran yang lebih tinggi.');
                }
            }
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
