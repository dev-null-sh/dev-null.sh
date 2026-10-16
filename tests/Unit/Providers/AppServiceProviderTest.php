<?php

declare(strict_types=1);

use App\Http\Middleware\ThrottleCommits;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
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

it('tells json requests to slow down without flashing', function (): void {
    $user = User::factory()->create();

    $request = Request::create('/commits/01a13f6e-ebdd-72c7-bf9d-be2a9b2315a3/replies', 'POST', server: ['HTTP_ACCEPT' => 'application/json']);
    $request->setUserResolver(fn (): User => $user);

    RateLimiter::increment("commits:{$user->id}", 60, 10);

    $response = new ThrottleCommits()->handle($request, fn (): Response => response('Committed'));

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

it('gives each developer their own search budget, and guests a smaller one per address', function (string $name, array $forDevelopers, array $forGuests): void {
    $user = User::factory()->create();

    $limiter = RateLimiter::limiter($name);

    assert($limiter instanceof Closure);

    $signedIn = Request::create('/search', server: ['REMOTE_ADDR' => '203.0.113.7']);
    $signedIn->setUserResolver(fn (): User => $user);

    $guest = Request::create('/search', server: ['REMOTE_ADDR' => '203.0.113.7']);

    $budgets = fn (Request $request): array => collect(Arr::wrap($limiter($request)))
        ->map(fn (Limit $limit): array => [$limit->maxAttempts, $limit->decaySeconds])
        ->all();

    expect($budgets($signedIn))->toBe($forDevelopers)
        ->and($budgets($guest))->toBe($forGuests)
        ->and(Arr::wrap($limiter($signedIn))[0]->key)->toContain($user->id)
        ->and(Arr::wrap($limiter($guest))[0]->key)->toContain('203.0.113.7');
})->with([
    'developer search' => ['user-search', [[120, 60]], [[60, 60]]],
    'mention suggestions' => ['mention-suggestions', [[120, 60]], [[60, 60]]],
    'commit search' => ['commit-search', [[30, 60], [500, 86400]], [[15, 60], [200, 86400]]],
]);
