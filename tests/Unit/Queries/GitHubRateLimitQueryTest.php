<?php

declare(strict_types=1);

use App\Queries\GitHubRateLimitQuery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['services.github.client_id' => 'client-id', 'services.github.client_secret' => 'client-secret']);
});

it('is only available when GitHub credentials are configured', function (?string $clientId, ?string $clientSecret, bool $isConfigured): void {
    config(['services.github.client_id' => $clientId, 'services.github.client_secret' => $clientSecret]);

    expect(resolve(GitHubRateLimitQuery::class)->isConfigured())->toBe($isConfigured);
})->with([
    [null, null, false],
    ['client-id', null, false],
    ['', 'client-secret', false],
    ['client-id', 'client-secret', true],
]);

it('reads the core rate limit as the OAuth app, and caches it for a minute', function (): void {
    $resetsAt = now()->addMinutes(30);

    Http::fake([
        'api.github.com/rate_limit' => Http::response([
            'resources' => ['core' => ['limit' => 5000, 'remaining' => 4321, 'reset' => $resetsAt->getTimestamp()]],
        ]),
    ]);

    $query = resolve(GitHubRateLimitQuery::class);

    expect($query->get())->toBe(['remaining' => 4321, 'limit' => 5000, 'resets_at' => $resetsAt->toIso8601String()])
        ->and($query->get())->toBe(['remaining' => 4321, 'limit' => 5000, 'resets_at' => $resetsAt->toIso8601String()]);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode('client-id:client-secret')));

    $this->travel(61)->seconds();
    $query->get();

    Http::assertSentCount(2);
});

it('is null when GitHub cannot be reached or answers with something unexpected', function (mixed $response): void {
    Http::fake(['api.github.com/rate_limit' => $response]);

    expect(resolve(GitHubRateLimitQuery::class)->get())->toBeNull();
})->with([
    'an error' => fn () => Http::response(['message' => 'Bad credentials'], 401),
    'no core limit' => fn () => Http::response(['resources' => []]),
    'a timeout' => fn () => Http::failedConnection(),
]);
