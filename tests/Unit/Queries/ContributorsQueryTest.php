<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Queries\ContributorsQuery;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

it('lists everyone with a commit, as GitHub counts them', function (): void {
    $accounts = [gitHubContributor('xHeaven', 187), gitHubContributor('jubianchi', 1)];
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('contributors')->andReturnUsing(fn (string $repository, int $page, int $perPage): array => array_slice($accounts, ($page - 1) * $perPage, $perPage));

    expect(resolve(ContributorsQuery::class)->get())->toBe([
        ['login' => 'xHeaven', 'commits' => 187, 'handle' => null],
        ['login' => 'jubianchi', 'commits' => 1, 'handle' => null],
    ]);
});

it('leaves out the bots and the agents, whatever case they are written in', function (): void {
    $accounts = [
        gitHubContributor('xHeaven', 187),
        gitHubContributor('ampagent', 8),
        gitHubContributor('Claude', 2),
        gitHubContributor('dependabot', 4, 'Bot'),
    ];
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('contributors')->andReturnUsing(fn (string $repository, int $page, int $perPage): array => array_slice($accounts, ($page - 1) * $perPage, $perPage));

    expect(resolve(ContributorsQuery::class)->get())->toBe([['login' => 'xHeaven', 'commits' => 187, 'handle' => null]]);
});

it('reads every page GitHub has, up to a thousand people', function (int $contributors, int $expected, int $requests): void {
    $accounts = array_map(fn (int $number): array => gitHubContributor("dev{$number}", 2000 - $number), range(1, $contributors));
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('contributors')->andReturnUsing(fn (string $repository, int $page, int $perPage): array => array_slice($accounts, ($page - 1) * $perPage, $perPage));

    expect(resolve(ContributorsQuery::class)->get())->toHaveCount($expected);

    $github->shouldHaveReceived('contributors')->times($requests);
})->with([
    'one page' => [100, 100, 2],
    'two pages' => [150, 150, 2],
    'more than ten pages' => [1200, 1000, 10],
]);

it('keeps reading when a page is full but some of it is left out', function (): void {
    $page = array_map(fn (int $number): array => gitHubContributor("dev{$number}", 200 - $number), range(1, 99));

    $accounts = [...$page, gitHubContributor('dependabot', 4, 'Bot'), gitHubContributor('xHeaven', 1)];
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('contributors')->andReturnUsing(fn (string $repository, int $page, int $perPage): array => array_slice($accounts, ($page - 1) * $perPage, $perPage));

    expect(resolve(ContributorsQuery::class)->get())->toHaveCount(100);

    $github->shouldHaveReceived('contributors')->times(2);
});

it('skips what GitHub sends without a login, an account or a count', function (): void {
    $accounts = [
        ['login' => 'ghost', 'id' => 1, 'type' => 'User'],
        ['id' => 2, 'contributions' => 3, 'type' => 'User'],
        ['login' => 'nobody', 'contributions' => 3, 'type' => 'User'],
        gitHubContributor('xHeaven', 187),
    ];
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('contributors')->andReturnUsing(fn (string $repository, int $page, int $perPage): array => array_slice($accounts, ($page - 1) * $perPage, $perPage));

    expect(resolve(ContributorsQuery::class)->get())->toBe([['login' => 'xHeaven', 'commits' => 187, 'handle' => null]]);
});

it('asks GitHub again an hour later, not before', function (): void {
    $accounts = [gitHubContributor('xHeaven', 187)];
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('contributors')->andReturnUsing(fn (string $repository, int $page, int $perPage): array => array_slice($accounts, ($page - 1) * $perPage, $perPage));

    resolve(ContributorsQuery::class)->get();
    $this->travel(59)->minutes();
    resolve(ContributorsQuery::class)->get();

    $github->shouldHaveReceived('contributors')->times(1);

    $this->travel(2)->minutes();
    resolve(ContributorsQuery::class)->get();

    $github->shouldHaveReceived('contributors')->times(2);
});

it('keeps an answer with nobody in it for the hour, since GitHub did answer', function (): void {
    $accounts = [gitHubContributor('claude', 2)];
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('contributors')->andReturnUsing(fn (string $repository, int $page, int $perPage): array => array_slice($accounts, ($page - 1) * $perPage, $perPage));

    expect(resolve(ContributorsQuery::class)->get())->toBe([])
        ->and(Cache::get('about:contributor-accounts:'.config()->string('services.github.repository')))->toBe([]);

    $this->travel(2)->minutes();
    resolve(ContributorsQuery::class)->get();

    $github->shouldHaveReceived('contributors')->times(1);
});

it('has nobody to show while GitHub is unreachable, and tries again a minute later', function (): void {
    $attempts = 0;
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('contributors')->andReturnUsing(function () use (&$attempts): never {
        $attempts++;

        throw new ConnectionException('timeout');
    });

    expect(resolve(ContributorsQuery::class)->get())->toBeNull();

    resolve(ContributorsQuery::class)->get();
    expect($attempts)->toBe(1);

    $this->travel(61)->seconds();
    resolve(ContributorsQuery::class)->get();

    expect($attempts)->toBe(2);
});

it('has nobody to show when GitHub answers with an error', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('contributors')->andThrow(new RequestException(new Response(new Psr7Response(404))));

    expect(resolve(ContributorsQuery::class)->get())->toBeNull();
});

it('discards a partial list after a later page fails and recovers when the failure expires', function (): void {
    $firstPage = array_map(fn (int $number): array => gitHubContributor("dev{$number}", 200 - $number), range(1, 100));
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('contributors')->andReturnUsing(
        fn (): array => $firstPage,
        fn () => throw new RequestException(new Response(new Psr7Response(503))),
        fn (): array => [gitHubContributor('recovered', 12)],
    );

    expect(resolve(ContributorsQuery::class)->get())->toBeNull();
    expect(resolve(ContributorsQuery::class)->get())->toBeNull();
    $github->shouldHaveReceived('contributors')->times(2);

    $this->travel(61)->seconds();

    expect(resolve(ContributorsQuery::class)->get())->toBe([['login' => 'recovered', 'commits' => 12, 'handle' => null]]);
    $github->shouldHaveReceived('contributors')->times(3);
});
