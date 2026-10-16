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

function throttledCommit(User $user, string $route = 'commit.store', array $input = []): Symfony\Component\HttpFoundation\Response
{
    $request = Request::create('/commits', 'POST', $input);
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

it('counts every part of a series and refuses a series that does not fit', function (): void {
    $user = User::factory()->create();

    throttledCommit($user, 'commit-series.store', ['bodies' => ['One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven']]);

    expect(RateLimiter::attempts("commits:{$user->id}"))->toBe(7)
        ->and(throttledCommit($user, 'commit-series.store', ['bodies' => ['One', 'Two', 'Three', 'Four']]))->toBeInstanceOf(RedirectResponse::class)
        ->and(RateLimiter::attempts("commits:{$user->id}"))->toBe(7)
        ->and(throttledCommit($user, 'commit-series.store', ['bodies' => ['One', 'Two', 'Three']])->getContent())->toBe('Committed')
        ->and(RateLimiter::attempts("commits:{$user->id}"))->toBe(10);
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
