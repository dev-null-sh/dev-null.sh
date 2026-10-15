<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Support\SessionKey;

it('only allows admins to moderate', function (): void {
    config(['app.admins' => ['admin']]);

    $admin = User::factory()->create(['username' => 'admin']);
    $user = User::factory()->create();

    expect(Gate::forUser($admin)->allows('moderate'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('moderate'))->toBeFalse();
});

it('limits each user to 10 commits a minute', function (): void {
    $user = User::factory()->create();

    $request = Request::create('/commits', 'POST');
    $request->setUserResolver(fn (): User => $user);

    $limiter = RateLimiter::limiter('commits');

    assert($limiter instanceof Closure);

    $limit = $limiter($request);

    expect($limit)->toBeInstanceOf(Limit::class)
        ->and($limit->key)->toBe($user->id)
        ->and($limit->maxAttempts)->toBe(10)
        ->and($limit->decaySeconds)->toBe(60);

    $response = ($limit->responseCallback)();

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and(session(SessionKey::FLASH_DATA))->toBe([
            'toast' => [
                'type' => 'error',
                'message' => 'Slow down: 10 commits a minute. Even CI takes breaks.',
            ],
        ]);
});
