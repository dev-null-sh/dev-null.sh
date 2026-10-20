<?php

declare(strict_types=1);

use App\Queries\RepositoryContributorsQuery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('paginates contributors, excludes bots, and sorts by commit count', function (): void {
    Http::fake(['api.github.com/repos/*/contributors*' => fn (Request $request) => Http::response($request['page'] === 1
        ? array_fill(0, 100, ['login' => 'automation[bot]', 'type' => 'Bot', 'contributions' => 1000])
        : [
            ['login' => 'second', 'type' => 'User', 'contributions' => 2],
            ['login' => 'first', 'type' => 'User', 'contributions' => 10],
        ])]);

    expect(resolve(RepositoryContributorsQuery::class)->get()->all())->toBe([
        ['login' => 'first', 'commits' => 10],
        ['login' => 'second', 'commits' => 2],
    ]);
    Http::assertSentCount(2);
});

it('shares the cache between visitors and refreshes after one hour', function (): void {
    Http::fake(['api.github.com/repos/*/contributors*' => Http::sequence()
        ->push([['login' => 'first', 'type' => 'User', 'contributions' => 1]])
        ->push([['login' => 'first', 'type' => 'User', 'contributions' => 2]])]);
    $query = resolve(RepositoryContributorsQuery::class);

    expect($query->get()->first()['commits'])->toBe(1);
    $this->travel(59)->minutes();
    expect($query->get()->first()['commits'])->toBe(1);
    Http::assertSentCount(1);
    $this->travel(2)->minutes();
    expect($query->get()->first()['commits'])->toBe(2);
    Http::assertSentCount(2);
});

it('keeps the page available during a GitHub outage and caches the failed attempt', function (): void {
    Http::fake(['api.github.com/repos/*/contributors*' => Http::failedConnection()]);
    $query = resolve(RepositoryContributorsQuery::class);

    expect($query->get())->toBeEmpty()->and($query->get())->toBeEmpty();
    Http::assertSentCount(1);
});
