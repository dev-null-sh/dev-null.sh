<?php

declare(strict_types=1);

use App\Actions\CreateCommitTopics;
use App\Actions\UpdateCommitTopics;
use App\Enums\ProfileType;
use App\Models\Commit;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, mixed>  $account
 * @return array<string, mixed>
 */
function gitHubOrganization(array $account = []): array
{
    return [
        'login' => 'laravel',
        'id' => 958072,
        'type' => 'Organization',
        'name' => 'Laravel',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/958072?v=4',
        'bio' => 'The PHP framework for web artisans.',
        'location' => null,
        'blog' => 'laravel.com',
        'company' => null,
        ...$account,
    ];
}

it('links the organizations GitHub has to the commit, from their GitHub profile', function (): void {
    Http::fake([
        'api.github.com/users/laravel' => Http::response(gitHubOrganization()),
    ]);

    $commit = Commit::factory()->create(['body' => 'Thanks @Laravel!', 'mentions' => []]);

    resolve(UpdateCommitTopics::class)->handle($commit);

    $organization = Profile::query()->where('type', ProfileType::Organization)->sole();

    expect($organization)
        ->github_id->toBe('958072')
        ->handle->toBe('laravel')
        ->name->toBe('Laravel')
        ->avatar_url->toBe('https://avatars.githubusercontent.com/u/958072?v=4')
        ->bio->toBe('The PHP framework for web artisans.')
        ->location->toBeNull()
        ->website->toBe('https://laravel.com')
        ->and($commit->refresh()->organization_mentions)->toBe(['laravel' => $organization->id]);
});

it('links nothing to a commit erased while its handles were looked up', function (bool $gone): void {
    $commit = Commit::factory()->create(['body' => 'Thanks @laravel', 'mentions' => []]);

    Http::fake(function () use ($commit, $gone) {
        $gone ? $commit->forceDelete() : $commit->update(['body' => '', 'mentions' => null]);

        return Http::response(gitHubOrganization());
    });

    resolve(UpdateCommitTopics::class)->handle($commit);

    expect(Profile::query()->where('handle', 'laravel')->exists())->toBeTrue();
    $this->assertDatabaseEmpty('commit_topics');
})->with(['content erased' => false, 'deleted' => true]);

it('only asks GitHub about the handles that are neither a user nor a known organization', function (): void {
    Http::fake();

    $tom = User::factory()->create(['handle' => 'tom']);
    $organization = Profile::factory()->organization()->create(['handle' => 'laravel']);
    $commit = Commit::factory()->create(['body' => 'Hi @tom, @laravel', 'mentions' => ['tom' => $tom->id]]);

    resolve(UpdateCommitTopics::class)->handle($commit);

    Http::assertNothingSent();
    expect($commit->refresh()->organization_mentions)->toBe(['laravel' => $organization->id]);
});

it('does nothing for a commit that only mentions users', function (): void {
    Http::fake();

    $tom = User::factory()->create(['handle' => 'tom']);
    $commit = Commit::factory()->create(['body' => 'Hi @tom', 'mentions' => ['tom' => $tom->id]]);

    resolve(UpdateCommitTopics::class)->handle($commit);

    Http::assertNothingSent();
    expect($commit->refresh()->organization_mentions)->toBeNull();
});

it('does not ask about a handle again for a day once it is not an organization', function (): void {
    Http::fake([
        'api.github.com/users/octocat' => Http::response(gitHubOrganization(['login' => 'octocat', 'id' => 583231, 'type' => 'User'])),
        'api.github.com/users/nobody' => Http::response(status: 404),
    ]);

    $commit = Commit::factory()->create(['body' => '@octocat @nobody', 'mentions' => []]);

    resolve(UpdateCommitTopics::class)->handle($commit);
    resolve(UpdateCommitTopics::class)->handle($commit);

    Http::assertSentCount(2);
    expect(Profile::query()->where('type', ProfileType::Organization)->count())->toBe(0)
        ->and($commit->refresh()->organization_mentions)->toBeNull();

    $this->travel(2)->days();

    resolve(UpdateCommitTopics::class)->handle($commit);

    Http::assertSentCount(4);
});

it('refreshes a known organization once its profile is a day old', function (): void {
    Http::fake([
        'api.github.com/user/958072' => Http::response(gitHubOrganization(['name' => 'Laravel, Inc.'])),
    ]);

    $organization = Profile::factory()->organization()->create(['github_id' => '958072', 'handle' => 'laravel', 'github_synced_at' => now()->subHours(Profile::FRESH_FOR_HOURS)->subMinute()]);
    $commit = Commit::factory()->create(['body' => '@laravel', 'mentions' => []]);

    resolve(UpdateCommitTopics::class)->handle($commit);

    expect($organization->refresh())
        ->name->toBe('Laravel, Inc.')
        ->github_synced_at->toDateTimeString()->toBe(now()->toDateTimeString())
        ->and($commit->refresh()->organization_mentions)->toBe(['laravel' => $organization->id]);
});

it('deletes a known organization GitHub no longer has, and unlinks the commits that mention it', function (): void {
    Http::fake([
        'api.github.com/user/958072' => Http::response(status: 404),
    ]);

    $organization = Profile::factory()->organization()->create(['github_id' => '958072', 'handle' => 'laravel', 'github_synced_at' => now()->subDays(2)]);
    $earlier = Commit::factory()->create(['body' => 'Thanks @laravel', 'mentions' => []]);
    resolve(CreateCommitTopics::class)->handle($earlier, ['laravel' => $organization->id]);
    $commit = Commit::factory()->create(['body' => '@laravel', 'mentions' => []]);

    resolve(UpdateCommitTopics::class)->handle($commit);

    expect(Profile::query()->where('type', ProfileType::Organization)->count())->toBe(0)
        ->and($commit->refresh()->organization_mentions)->toBeNull()
        ->and($earlier->refresh()->organization_mentions)->toBeNull();
});

it('keeps the organizations the commit already links to', function (): void {
    Http::fake([
        'api.github.com/users/*' => Http::response(status: 404),
    ]);

    $organization = Profile::factory()->organization()->create(['handle' => 'now-renamed']);
    $commit = Commit::factory()->create(['body' => '@renamed', 'mentions' => []]);
    resolve(CreateCommitTopics::class)->handle($commit, ['renamed' => $organization->id]);

    resolve(UpdateCommitTopics::class)->handle($commit);

    expect($commit->refresh()->organization_mentions)->toBe(['renamed' => $organization->id]);
});

it('asks GitHub about five handles per commit at most', function (): void {
    Http::fake([
        'api.github.com/users/*' => Http::response(status: 404),
    ]);

    $commit = Commit::factory()->create(['body' => '@one @two @three @four @five @six', 'mentions' => []]);

    resolve(UpdateCommitTopics::class)->handle($commit);

    Http::assertSentCount(5);
});

it('waits for the rate limit to reset once few requests are left, and says when to try again', function (): void {
    $resetsAt = now()->addMinutes(30)->startOfSecond();

    Http::fake([
        'api.github.com/users/laravel' => Http::response(gitHubOrganization(), headers: [
            'X-RateLimit-Remaining' => '999',
            'X-RateLimit-Reset' => (string) $resetsAt->getTimestamp(),
        ]),
        'api.github.com/users/*' => Http::response(status: 404),
    ]);

    $commit = Commit::factory()->create(['body' => '@laravel @symfony', 'mentions' => []]);

    $retryAt = resolve(UpdateCommitTopics::class)->handle($commit);

    Http::assertSentCount(1);
    expect($retryAt?->getTimestamp())->toBe($resetsAt->getTimestamp())
        ->and($commit->refresh()->organization_mentions)->toHaveKey('laravel')
        ->and(Cache::has('github-organizations:paused'))->toBeTrue();

    $this->travelTo($resetsAt->addSecond());

    expect(resolve(UpdateCommitTopics::class)->handle($commit))->toBeNull();
    Http::assertSentCount(2);
});

it('does not try again when the last lookup is the one that pauses', function (): void {
    Http::fake([
        'api.github.com/users/laravel' => Http::response(gitHubOrganization(), headers: [
            'X-RateLimit-Remaining' => '999',
            'X-RateLimit-Reset' => (string) now()->addMinutes(30)->getTimestamp(),
        ]),
    ]);

    $commit = Commit::factory()->create(['body' => '@laravel', 'mentions' => []]);

    expect(resolve(UpdateCommitTopics::class)->handle($commit))->toBeNull()
        ->and(Cache::has('github-organizations:paused'))->toBeTrue();
});

it('reads the pause from cache stores that keep numbers as strings', function (): void {
    Http::fake();

    $resetsAt = now()->addMinutes(30)->startOfSecond();
    Cache::put('github-organizations:paused', (string) $resetsAt->getTimestamp(), $resetsAt);
    $commit = Commit::factory()->create(['body' => '@laravel', 'mentions' => []]);

    expect(resolve(UpdateCommitTopics::class)->handle($commit)?->getTimestamp())->toBe($resetsAt->getTimestamp());
    Http::assertNothingSent();
});

it('keeps asking while enough requests are left', function (): void {
    Http::fake([
        'api.github.com/users/*' => Http::response(status: 404, headers: ['X-RateLimit-Remaining' => '1000']),
    ]);

    $commit = Commit::factory()->create(['body' => '@laravel @symfony', 'mentions' => []]);

    expect(resolve(UpdateCommitTopics::class)->handle($commit))->toBeNull();
    Http::assertSentCount(2);
    expect(Cache::has('github-organizations:paused'))->toBeFalse();
});

it('throws when GitHub fails, so the job tries again', function (): void {
    Http::fake([
        'api.github.com/users/laravel' => Http::response(status: 502),
    ]);

    $commit = Commit::factory()->create(['body' => '@laravel', 'mentions' => []]);

    expect(fn () => resolve(UpdateCommitTopics::class)->handle($commit))->toThrow(RequestException::class);
});
