<?php

declare(strict_types=1);

use App\Actions\CreateCommitTopics;
use App\Actions\UpdateCommitTopics;
use App\Contracts\Services\GitHubProvider;
use App\Enums\ProfileType;
use App\Models\Commit;
use App\Models\Profile;
use App\Models\User;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

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
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('accountByLogin')->with('laravel')->andReturn(gitHubOrganization());

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

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('accountByLogin')->with('laravel')->andReturnUsing(function () use ($commit, $gone): array {
        $gone ? $commit->forceDelete() : $commit->update(['body' => '', 'mentions' => null]);

        return gitHubOrganization();
    });

    resolve(UpdateCommitTopics::class)->handle($commit);

    expect(Profile::query()->where('handle', 'laravel')->exists())->toBeTrue();
    $this->assertDatabaseEmpty('commit_topics');
})->with(['content erased' => false, 'deleted' => true]);

it('only asks GitHub about the handles that are neither a user nor a known organization', function (): void {
    $github = $this->mock(GitHubProvider::class);

    $tom = User::factory()->create(['handle' => 'tom']);
    $organization = Profile::factory()->organization()->create(['handle' => 'laravel']);
    $commit = Commit::factory()->create(['body' => 'Hi @tom, @laravel', 'mentions' => ['tom' => $tom->id]]);

    resolve(UpdateCommitTopics::class)->handle($commit);

    $github->shouldNotHaveReceived('accountByLogin');
    $github->shouldNotHaveReceived('account');

    expect($commit->refresh()->organization_mentions)->toBe(['laravel' => $organization->id]);
});

it('does nothing for a commit that only mentions users', function (): void {
    $github = $this->mock(GitHubProvider::class);

    $tom = User::factory()->create(['handle' => 'tom']);
    $commit = Commit::factory()->create(['body' => 'Hi @tom', 'mentions' => ['tom' => $tom->id]]);

    resolve(UpdateCommitTopics::class)->handle($commit);

    $github->shouldNotHaveReceived('accountByLogin');
    $github->shouldNotHaveReceived('rateLimit');

    expect($commit->refresh()->organization_mentions)->toBeNull();
});

it('does not ask about a handle again for a day once it is not an organization', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('accountByLogin')->with('octocat')->andReturn(gitHubOrganization(['login' => 'octocat', 'id' => 583231, 'type' => 'User']));
    $github->shouldReceive('accountByLogin')->with('nobody')->andReturnNull();

    $commit = Commit::factory()->create(['body' => '@octocat @nobody', 'mentions' => []]);

    resolve(UpdateCommitTopics::class)->handle($commit);
    resolve(UpdateCommitTopics::class)->handle($commit);

    $github->shouldHaveReceived('accountByLogin')->twice();
    expect(Profile::query()->where('type', ProfileType::Organization)->count())->toBe(0)
        ->and($commit->refresh()->organization_mentions)->toBeNull();

    $this->travel(2)->days();

    resolve(UpdateCommitTopics::class)->handle($commit);

    $github->shouldHaveReceived('accountByLogin')->times(4);
});

it('refreshes a known organization once its profile is a day old', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('account')->with('958072')->andReturn(gitHubOrganization(['name' => 'Laravel, Inc.']));

    $organization = Profile::factory()->organization()->create(['github_id' => '958072', 'handle' => 'laravel', 'github_synced_at' => now()->subHours(Profile::FRESH_FOR_HOURS)->subMinute()]);
    $commit = Commit::factory()->create(['body' => '@laravel', 'mentions' => []]);

    resolve(UpdateCommitTopics::class)->handle($commit);

    expect($organization->refresh())
        ->name->toBe('Laravel, Inc.')
        ->github_synced_at->toDateTimeString()->toBe(now()->toDateTimeString())
        ->and($commit->refresh()->organization_mentions)->toBe(['laravel' => $organization->id]);
});

it('deletes a known organization GitHub no longer has, and unlinks the commits that mention it', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('account')->with('958072')->andReturnNull();

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
    $organization = Profile::factory()->organization()->create(['handle' => 'now-renamed']);
    $commit = Commit::factory()->create(['body' => '@renamed', 'mentions' => []]);
    resolve(CreateCommitTopics::class)->handle($commit, ['renamed' => $organization->id]);

    resolve(UpdateCommitTopics::class)->handle($commit);

    expect($commit->refresh()->organization_mentions)->toBe(['renamed' => $organization->id]);
});

it('asks GitHub about five handles per commit at most', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('accountByLogin')->andReturnNull();

    $commit = Commit::factory()->create(['body' => '@one @two @three @four @five @six', 'mentions' => []]);

    resolve(UpdateCommitTopics::class)->handle($commit);

    $github->shouldHaveReceived('accountByLogin')->times(5);
});

it('waits for the rate limit to reset once few requests are left, and says when to try again', function (): void {
    $resetsAt = now()->addMinutes(30)->startOfSecond();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturn(
        ['remaining' => 999, 'limit' => 5000, 'resets_at' => $resetsAt],
        ['remaining' => 4999, 'limit' => 5000, 'resets_at' => $resetsAt->addHour()],
    );
    $github->shouldReceive('accountByLogin')->with('laravel')->andReturn(gitHubOrganization());
    $github->shouldReceive('accountByLogin')->with('symfony')->andReturnNull();

    $commit = Commit::factory()->create(['body' => '@laravel @symfony', 'mentions' => []]);

    $retryAt = resolve(UpdateCommitTopics::class)->handle($commit);

    $github->shouldNotHaveReceived('accountByLogin');
    expect($retryAt?->getTimestamp())->toBe($resetsAt->getTimestamp())
        ->and($commit->refresh()->organization_mentions)->toBeNull()
        ->and(Cache::has('github-organizations:paused'))->toBeTrue();

    $this->travelTo($resetsAt->addSecond());

    expect(resolve(UpdateCommitTopics::class)->handle($commit))->toBeNull()
        ->and($commit->refresh()->organization_mentions)->toHaveKey('laravel');
    $github->shouldHaveReceived('accountByLogin')->twice();
});

it('reads the pause from cache stores that keep numbers as strings', function (): void {
    $github = $this->mock(GitHubProvider::class);

    $resetsAt = now()->addMinutes(30)->startOfSecond();
    Cache::put('github-organizations:paused', (string) $resetsAt->getTimestamp(), $resetsAt);
    $commit = Commit::factory()->create(['body' => '@laravel', 'mentions' => []]);

    expect(resolve(UpdateCommitTopics::class)->handle($commit)?->getTimestamp())->toBe($resetsAt->getTimestamp());
    $github->shouldNotHaveReceived('rateLimit');
    $github->shouldNotHaveReceived('accountByLogin');
});

it('keeps asking while enough requests are left', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturn(['remaining' => 1000, 'limit' => 5000, 'resets_at' => now()->addHour()]);
    $github->shouldReceive('accountByLogin')->andReturnNull();

    $commit = Commit::factory()->create(['body' => '@laravel @symfony', 'mentions' => []]);

    expect(resolve(UpdateCommitTopics::class)->handle($commit))->toBeNull();
    $github->shouldHaveReceived('accountByLogin')->twice();
    expect(Cache::has('github-organizations:paused'))->toBeFalse();
});

it('throws when GitHub fails, so the job tries again', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('accountByLogin')->with('laravel')->andThrow(new RequestException(new Response(new Psr7Response(502))));

    $commit = Commit::factory()->create(['body' => '@laravel', 'mentions' => []]);

    expect(fn () => resolve(UpdateCommitTopics::class)->handle($commit))->toThrow(RequestException::class);
});
