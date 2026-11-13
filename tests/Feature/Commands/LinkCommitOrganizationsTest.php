<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\Commit;
use App\Models\Profile;
use App\Models\User;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

it('links the approved commits written before organizations existed', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('accountByLogin')->with('nobody')->andReturnNull();
    $github->shouldReceive('accountByLogin')->with('laravel')->andReturn(['login' => 'laravel', 'id' => 958072, 'type' => 'Organization', 'name' => 'Laravel', 'avatar_url' => null, 'bio' => null, 'location' => null, 'blog' => '', 'company' => null]);

    $tom = User::factory()->create(['handle' => 'tom']);
    $first = Commit::factory()->create(['body' => 'Thanks @laravel', 'mentions' => []]);
    $second = Commit::factory()->create(['body' => '@Laravel and @nobody', 'mentions' => []]);
    $pending = Commit::factory()->pending()->create(['body' => '@pending-org', 'mentions' => []]);
    Commit::factory()->create(['body' => 'Hi @tom', 'mentions' => ['tom' => $tom->id]]);
    Commit::factory()->create(['body' => 'No mention']);

    $this->artisan('commits:link-organizations')
        ->expectsOutputToContain('Looked up the organizations of 2 commits.')
        ->assertSuccessful();

    $laravel = Profile::query()->where('handle', 'laravel')->sole();

    expect($first->refresh()->organization_mentions)->toBe(['laravel' => $laravel->id])
        ->and($second->refresh()->organization_mentions)->toBe(['laravel' => $laravel->id])
        ->and($pending->refresh()->organization_mentions)->toBeNull();
    $github->shouldHaveReceived('accountByLogin')->twice();
});

it('stops once GitHub pauses the lookups, to be run again after the reset', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldNotReceive('accountByLogin');

    $resetsAt = now()->addMinutes(30)->startOfSecond();
    Cache::put('github-organizations:paused', $resetsAt->getTimestamp(), $resetsAt);
    Commit::factory()->create(['body' => 'Thanks @laravel', 'mentions' => []]);

    $this->artisan('commits:link-organizations')
        ->expectsOutputToContain("GitHub rate limit reached after 0 commits. Run the command again after {$resetsAt->toDateTimeString()} UTC.")
        ->assertFailed();
});

it('stops when GitHub refuses the requests', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('accountByLogin')->andThrow(new RequestException(new Response(new Psr7Response(429))));

    Commit::factory()->create(['body' => '@first', 'mentions' => []]);
    Commit::factory()->create(['body' => '@second', 'mentions' => []]);

    $this->artisan('commits:link-organizations')
        ->expectsOutputToContain('GitHub rate limit reached after 0 commits. Run the command again later.')
        ->assertFailed();

    $github->shouldHaveReceived('accountByLogin')->once();
});

it('goes on with the other commits when GitHub fails for one', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('accountByLogin')->with('nobody')->andReturnNull();
    $github->shouldReceive('accountByLogin')->with('broken')->andThrow(new RequestException(new Response(new Psr7Response(502))));

    $broken = Commit::factory()->create(['body' => '@broken', 'mentions' => []]);
    Commit::factory()->create(['body' => '@nobody', 'mentions' => []]);

    $this->artisan('commits:link-organizations')
        ->expectsOutputToContain("Could not look up the organizations of commit {$broken->id}.")
        ->expectsOutputToContain('Looked up the organizations of 1 commits.')
        ->assertFailed();
});
