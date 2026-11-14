<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;

it('renders the partners page for guests', function (): void {
    $this->get(route('partner.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('partner/index'));
});

it('renders the partners page for signed in users', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('partner.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('partner/index'));
});

it('describes the partners page to search engines and link previews', function (): void {
    $this->get(route('partner.index'))
        ->assertOk()
        ->assertSee('<title>Partners - /dev/null</title>', false)
        ->assertSee('<link rel="canonical" href="'.route('partner.index').'">', false)
        ->assertSee(url('/images/partners/og.png'), false)
        ->assertSee('"@type":"WebPage"', false);
});

it('shows the partners in their tiers', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('publicSponsors')->andReturn([
        ['login' => 'laravel', 'github_id' => '78116372', 'amount_in_cents' => 2_600_000],
        ['login' => 'octocat', 'github_id' => '4194847', 'amount_in_cents' => 60_000],
    ]);

    $this->get(route('partner.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('tiers', [
            ['amount' => 50000, 'partners' => []],
            ['amount' => 20000, 'partners' => [['login' => 'laravel', 'handle' => null]]],
            ['amount' => 5000, 'partners' => []],
            ['amount' => 500, 'partners' => [['login' => 'octocat', 'handle' => null]]],
            ['amount' => 0, 'partners' => []],
        ]));
});

it('shows no tiers when GitHub cannot be reached', function (): void {
    $this->mock(GitHubProvider::class)->shouldReceive('publicSponsors')->andThrow(new ConnectionException('timeout'));

    $this->get(route('partner.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('tiers', null));
});
