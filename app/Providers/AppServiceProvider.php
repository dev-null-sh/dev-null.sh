<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Services\DnsResolver;
use App\Models\User;
use App\Services\DnsResolvers\SystemDnsResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\ExceptionResponse;
use Inertia\Inertia;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DnsResolver::class, SystemDnsResolver::class);
    }

    public function boot(): void
    {
        $this->bootRateLimitingDefaults();
        $this->bootAuthorizationDefaults();
        $this->bootExceptionHandling();
    }

    private function bootAuthorizationDefaults(): void
    {
        Gate::define('moderate', fn (User $user): bool => $user->isAdmin());
    }

    private function bootExceptionHandling(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response): ExceptionResponse|RedirectResponse|null {
            // API routes render their own errors (JSON, or plain text for the `devnull commit` script)...
            if ($response->request->expectsJson() || $response->request->is('api/*')) {
                return null;
            }

            if ($response->statusCode() === 419) {
                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => __('Page expired: your session timed out before that went through. Try again.'),
                ]);

                return back();
            }

            if ($response->statusCode() === 500 && config('app.debug')) {
                return null;
            }

            if (! in_array($response->statusCode(), [403, 404, 429, 500, 503], true)) {
                return null;
            }

            return $response->render('error', [
                'status' => $response->statusCode(),
            ])->withSharedData();
        });
    }

    private function bootRateLimitingDefaults(): void
    {
        RateLimiter::for('commits', function (Request $request): Limit {
            $user = $request->user();
            assert($user instanceof User);

            // The `devnull commit` script prints plain text...
            if ($request->routeIs('cli-commit.store')) {
                return Limit::perMinute(10)->by($user->id)->response(
                    fn (Request $request, array $headers): Response => response(__('Slow down: 10 commits a minute. Even CI takes breaks.')."\n", 429, $headers)
                        ->header('Content-Type', 'text/plain; charset=utf-8'),
                );
            }

            return Limit::perMinute(10)->by($user->id)->response(function (): RedirectResponse {
                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => __('Slow down: 10 commits a minute. Even CI takes breaks.'),
                ]);

                return back();
            });
        });
    }
}
