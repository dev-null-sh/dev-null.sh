<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Support\SessionKey;

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

    $response = ($limit->responseCallback)($request, ['Retry-After' => 60]);

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and(session(SessionKey::FLASH_DATA))->toBe([
            'toast' => [
                'type' => 'error',
                'message' => 'Slow down: 10 commits a minute. Even CI takes breaks.',
            ],
        ]);
});

it('tells json requests to slow down without flashing', function (): void {
    $user = User::factory()->create();

    $request = Request::create('/commits/01a13f6e-ebdd-72c7-bf9d-be2a9b2315a3/replies', 'POST', server: ['HTTP_ACCEPT' => 'application/json']);
    $request->setUserResolver(fn (): User => $user);

    $limiter = RateLimiter::limiter('commits');

    assert($limiter instanceof Closure);

    $response = ($limiter($request)->responseCallback)($request, ['Retry-After' => 60]);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(429)
        ->and($response->headers->get('Retry-After'))->toBe('60')
        ->and($response->getData(true))->toBe(['message' => 'Slow down: 10 commits a minute. Even CI takes breaks.'])
        ->and(session(SessionKey::FLASH_DATA))->toBeNull();
});

it('limits each address to 60 public api requests a minute', function (): void {
    $request = Request::create('/api/v1/users/octocat', server: ['REMOTE_ADDR' => '203.0.113.7']);

    $limiter = RateLimiter::limiter('api');

    assert($limiter instanceof Closure);

    $limit = $limiter($request);

    expect($limit)->toBeInstanceOf(Limit::class)
        ->and($limit->key)->toBe('203.0.113.7')
        ->and($limit->maxAttempts)->toBe(60)
        ->and($limit->decaySeconds)->toBe(60);
});
