<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

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
        Paginator::useBootstrapFive();

        $this->configureRateLimiting();

        Vite::useStyleTagAttributes(function (?string $src, string $url, ?array $chunk, ?array $manifest) {
            if ($src !== null) {
                return [
                    'class' => preg_match("/(resources\/assets\/vendor\/scss\/(rtl\/)?core)-?.*/i", $src) ? 'template-customizer-core-css' : (preg_match("/(resources\/assets\/vendor\/scss\/(rtl\/)?theme)-?.*/i", $src) ? 'template-customizer-theme-css' : '')
                ];
            }
            return [];
        });
    }

    /**
     * Throttles applied by name from routes/api.php.
     *
     * Authenticated traffic is counted per user so one noisy device cannot
     * lock out everyone behind the same NAT; anonymous traffic falls back
     * to the IP address.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(90)->by($request->user()?->id ?: $request->ip());
        });

        // Sign-in, sign-up and password reset: tight, and keyed on the
        // credential as well as the IP.
        RateLimiter::for('auth', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($email . '|' . $request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        // Sending a reset code costs money and leaks timing; keep it rare.
        RateLimiter::for('password-reset', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return [
                Limit::perHour(3)->by($email),
                Limit::perHour(10)->by($request->ip()),
            ];
        });

        // Checking a code, and spending the token it returns. A six-digit
        // code is only 10^6 wide, so this sits on top of the per-code
        // attempt counter rather than replacing it.
        RateLimiter::for('otp', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(6)->by($email . '|' . $request->ip()),
                Limit::perHour(20)->by($email),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        // Writes are heavier than reads.
        RateLimiter::for('writes', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Unauthenticated content endpoints.
        RateLimiter::for('public', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });
    }
}
