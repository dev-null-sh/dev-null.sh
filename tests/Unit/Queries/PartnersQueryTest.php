<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use App\Queries\PartnersQuery;
use Illuminate\Http\Client\ConnectionException;

it('puts each public sponsor in the biggest tier their total reaches, one dollar short included', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('publicSponsors')->with('dev-null-sh')->andReturn([
        ['login' => 'laravel', 'github_id' => '78116372', 'amount_in_cents' => 4_999_900],
        ['login' => 'tighten', 'github_id' => '46025145', 'amount_in_cents' => 4_999_899],
        ['login' => 'spatie', 'github_id' => '65205545', 'amount_in_cents' => 499_900],
        ['login' => 'octocat', 'github_id' => '4194847', 'amount_in_cents' => 499_899],
        ['login' => 'xHeaven', 'github_id' => '68775106', 'amount_in_cents' => 49_900],
        ['login' => 'jubianchi', 'github_id' => '57972542', 'amount_in_cents' => 49_899],
    ]);

    expect(resolve(PartnersQuery::class)->get())->toBe([
        ['amount' => 50000, 'partners' => [['login' => 'laravel', 'handle' => null]]],
        ['amount' => 20000, 'partners' => [['login' => 'tighten', 'handle' => null]]],
        ['amount' => 5000, 'partners' => [['login' => 'spatie', 'handle' => null]]],
        ['amount' => 500, 'partners' => [['login' => 'octocat', 'handle' => null], ['login' => 'xHeaven', 'handle' => null]]],
        ['amount' => 0, 'partners' => [['login' => 'jubianchi', 'handle' => null]]],
    ]);
});

it('gives each partner their /dev/null handle, found by their GitHub account', function (): void {
    User::factory()->create(['github_id' => '583231', 'handle' => 'mona']);
    $this->mock(GitHubProvider::class)->shouldReceive('publicSponsors')->andReturn([
        ['login' => 'octocat', 'github_id' => '583231', 'amount_in_cents' => 100_000],
        ['login' => 'hubot', 'github_id' => '480938', 'amount_in_cents' => 100],
    ]);

    expect(resolve(PartnersQuery::class)->get())->toBe([
        ['amount' => 50000, 'partners' => []],
        ['amount' => 20000, 'partners' => []],
        ['amount' => 5000, 'partners' => []],
        ['amount' => 500, 'partners' => [['login' => 'octocat', 'handle' => 'mona']]],
        ['amount' => 0, 'partners' => [['login' => 'hubot', 'handle' => null]]],
    ]);
});

it('keeps every tier when the organization has no public sponsors', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('publicSponsors')->andReturn([]);

    expect(resolve(PartnersQuery::class)->get())->toBe([
        ['amount' => 50000, 'partners' => []],
        ['amount' => 20000, 'partners' => []],
        ['amount' => 5000, 'partners' => []],
        ['amount' => 500, 'partners' => []],
        ['amount' => 0, 'partners' => []],
    ]);
});

it('asks GitHub once an hour', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('publicSponsors')->andReturn([['login' => 'octocat', 'github_id' => '4194847', 'amount_in_cents' => 100_000]]);

    resolve(PartnersQuery::class)->get();
    $this->travel(59)->minutes();
    resolve(PartnersQuery::class)->get();
    $github->shouldHaveReceived('publicSponsors')->once();

    $this->travel(2)->minutes();
    resolve(PartnersQuery::class)->get();
    $github->shouldHaveReceived('publicSponsors')->twice();
});

it('is null when GitHub does not answer, and asks again after a minute', function (): void {
    $attempts = 0;
    $this->mock(GitHubProvider::class)->shouldReceive('publicSponsors')->andReturnUsing(function () use (&$attempts): never {
        $attempts++;

        throw new ConnectionException('timeout');
    });

    expect(resolve(PartnersQuery::class)->get())->toBeNull();

    resolve(PartnersQuery::class)->get();
    expect($attempts)->toBe(1);

    $this->travel(61)->seconds();
    resolve(PartnersQuery::class)->get();

    expect($attempts)->toBe(2);
});
