<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Queries\GitHubRateLimitQuery;
use Illuminate\Http\Client\ConnectionException;

it('reads the rate limit, and caches it for a minute', function (): void {
    $resetsAt = now()->addMinutes(30)->toImmutable();
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->twice()->andReturn(['remaining' => 4321, 'limit' => 5000, 'resets_at' => $resetsAt]);

    $query = resolve(GitHubRateLimitQuery::class);

    expect($query->get())->toBe(['remaining' => 4321, 'limit' => 5000, 'resets_at' => $resetsAt->toIso8601String()])
        ->and($query->get())->toBe(['remaining' => 4321, 'limit' => 5000, 'resets_at' => $resetsAt->toIso8601String()]);

    $this->travel(61)->seconds();
    $query->get();
});

it('is null when GitHub cannot say', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();

    expect(resolve(GitHubRateLimitQuery::class)->get())->toBeNull();
});

it('is null when GitHub cannot be reached', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andThrow(new ConnectionException('timeout'));

    expect(resolve(GitHubRateLimitQuery::class)->get())->toBeNull();
});
