<?php

declare(strict_types=1);

use App\Http\Middleware\ThrottleCommits;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Support\SessionKey;

it('limits each user to 10 commits a minute', function (): void {
    $user = User::factory()->create();

    $request = Request::create('/commits', 'POST');
    $request->setUserResolver(fn (): User => $user);

    $middleware = new ThrottleCommits;

    for ($attempt = 0; $attempt < 10; $attempt++) {
        expect($middleware->handle($request, fn (): Response => response('Committed'))->getContent())->toBe('Committed');
    }

    $response = $middleware->handle($request, fn (): Response => response('Committed'));

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and(session(SessionKey::FLASH_DATA))->toBe([
            'toast' => [
                'type' => 'error',
                'message' => 'Slow down: 10 commits a minute. Even CI takes breaks.',
            ],
        ]);
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
