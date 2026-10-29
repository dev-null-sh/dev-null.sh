<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('links the approved commits written before organizations existed', function (): void {
    Http::fake([
        'api.github.com/users/laravel' => Http::response(['login' => 'laravel', 'id' => 958072, 'type' => 'Organization', 'name' => 'Laravel', 'avatar_url' => null, 'bio' => null, 'location' => null, 'blog' => '', 'company' => null]),
        'api.github.com/users/*' => Http::response(status: 404),
    ]);

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
    Http::assertSentCount(2);
});

it('stops once GitHub pauses the lookups, to be run again after the reset', function (): void {
    Http::fake();

    $resetsAt = now()->addMinutes(30)->startOfSecond();
    Cache::put('github-organizations:paused', $resetsAt->getTimestamp(), $resetsAt);
    Commit::factory()->create(['body' => 'Thanks @laravel', 'mentions' => []]);

    $this->artisan('commits:link-organizations')
        ->expectsOutputToContain("GitHub rate limit reached after 0 commits. Run the command again after {$resetsAt->toDateTimeString()} UTC.")
        ->assertFailed();

    Http::assertNothingSent();
});

it('stops when GitHub refuses the requests', function (): void {
    Http::fake([
        'api.github.com/users/*' => Http::response(status: 429),
    ]);

    Commit::factory()->create(['body' => '@first', 'mentions' => []]);
    Commit::factory()->create(['body' => '@second', 'mentions' => []]);

    $this->artisan('commits:link-organizations')
        ->expectsOutputToContain('GitHub rate limit reached after 0 commits. Run the command again later.')
        ->assertFailed();

    Http::assertSentCount(1);
});

it('goes on with the other commits when GitHub fails for one', function (): void {
    Http::fake([
        'api.github.com/users/broken' => Http::response(status: 502),
        'api.github.com/users/*' => Http::response(status: 404),
    ]);

    $broken = Commit::factory()->create(['body' => '@broken', 'mentions' => []]);
    Commit::factory()->create(['body' => '@nobody', 'mentions' => []]);

    $this->artisan('commits:link-organizations')
        ->expectsOutputToContain("Could not look up the organizations of commit {$broken->id}.")
        ->expectsOutputToContain('Looked up the organizations of 1 commits.')
        ->assertFailed();
});
