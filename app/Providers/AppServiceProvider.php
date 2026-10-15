<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
    }

    private function bootAuthorizationDefaults(): void
    {
        Gate::define('moderate', fn (User $user): bool => $user->isAdmin());
    }

    private function bootRateLimitingDefaults(): void
    {
        RateLimiter::for('commits', function (Request $request): Limit {
            $user = $request->user();
            assert($user instanceof User);

            return Limit::perMinute(10)->by($user->id);
        });
    }
}
