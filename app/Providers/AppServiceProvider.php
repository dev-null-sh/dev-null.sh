<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\ExceptionResponse;
use Inertia\Inertia;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
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
            if ($response->request->expectsJson()) {
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
