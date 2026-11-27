<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(function () {
            return Password::min(8)
                           ->letters()
                           ->mixedCase()
                           ->numbers()
                           ->symbols();
        });

        Gate::policy(\App\Models\Notulen::class, \App\Policies\NotulenPolicy::class);
        Gate::policy(\App\Models\PerformanceEvaluation::class, \App\Policies\PerformanceEvaluationPolicy::class);

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Notifications\Events\NotificationSent::class,
            function ($event) {
                if ($event->notifiable instanceof \App\Models\User) {
                    $event->notifiable->clearNotificationCache();
                }
            }
        );

        \Illuminate\Support\Facades\RateLimiter::for('kiosk-scan', function (\Illuminate\Http\Request $request) {
            $token = (string) $request->input('token', $request->query('token', ''));
            $tokenHash = !empty($token) ? sha1($token) : 'no-token';
            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by($request->ip()),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($tokenHash),
            ];
        });

        \Illuminate\Support\Facades\RateLimiter::for('api-login', function (\Illuminate\Http\Request $request) {
            $email = (string) $request->input('email', '');
            $emailKey = !empty($email) ? 'api-login:' . \Illuminate\Support\Str::lower($email) : 'api-login:empty';
            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($request->ip()),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($emailKey),
            ];
        });
    }
}
