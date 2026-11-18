<?php

declare(strict_types=1);

use App\Http\Middleware\ThrottleCommits;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Support\SessionKey;

function throttledCommit(User $user, string $route = 'commit.store'): Symfony\Component\HttpFoundation\Response
{
    $request = Request::create('/commits', 'POST');
    $request->setUserResolver(fn (): User => $user);
    $request->setRouteResolver(fn (): Route => resolve(Router::class)->getRoutes()->getByName($route));

    return new ThrottleCommits()->handle($request, fn (): Response => response('Committed'));
}

it('lets each user commit 10 times a minute', function (): void {
    $user = User::factory()->create();

    for ($i = 0; $i < 10; $i++) {
        expect(throttledCommit($user)->getContent())->toBe('Committed');
    }

    $response = throttledCommit($user);

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and(session(SessionKey::FLASH_DATA))->toBe([
            'toast' => [
                'type' => 'error',
                'message' => 'Slow down: 10 commits a minute. Even CI takes breaks.',
            ],
        ])
        ->and(throttledCommit(User::factory()->create())->getContent())->toBe('Committed');
});

it('refuses a commit the count puts over the limit, even when the budget looked open a moment before', function (): void {
    $user = User::factory()->create();

    // Commits sent at the same moment each read room left, then the others are counted before this one is...
    RateLimiter::shouldReceive('tooManyAttempts')->andReturnFalse();
    RateLimiter::shouldReceive('increment')->once()->andReturn(11);
    RateLimiter::shouldReceive('availableIn')->andReturn(60);

    expect(throttledCommit($user))->toBeInstanceOf(RedirectResponse::class);
});

it('tells the commit script to slow down in plain text', function (): void {
    $user = User::factory()->create();

    RateLimiter::increment("commits:{$user->id}", 60, 10);

    $response = throttledCommit($user, 'cli-commit.store');

    expect($response->getStatusCode())->toBe(429)
        ->and($response->headers->get('Content-Type'))->toBe('text/plain; charset=utf-8')
        ->and($response->headers->get('Retry-After'))->toBe('60')
        ->and($response->getContent())->toBe("Slow down: 10 commits a minute. Even CI takes breaks.\n");
});
