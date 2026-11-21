<?php

declare(strict_types=1);

use App\Actions\UpdateUserProfile;
use App\Contracts\Services\GitHubProvider;
use App\Jobs\LookUpUserOrganizations;
use App\Models\SshKey;
use App\Models\User;
use App\Queries\GitHubReadmeQuery;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Queue;
use Tests\Support\SshKeyPair;

it('mirrors the github profile of the user', function (): void {
    $user = User::factory()->create([
        'github_id' => '583231',
        'handle' => 'old-handle',
        'email' => 'private@example.com',
        'has_sponsors_listing' => true,
    ]);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('account')->with($user->github_id)->andReturn([
        'login' => 'octocat',
        'name' => 'The Octocat',
        'email' => null,
        'avatar_url' => 'https://avatars.githubusercontent.com/u/583231?v=4',
        'bio' => ' I am the Octocat. ',
        'company' => '@github',
        'location' => 'San Francisco',
        'blog' => 'github.blog',
    ]);
    $github->shouldReceive('login')->with($user->github_id)->andReturn('octocat');
    $github->shouldReceive('sshKeys')->with('octocat')->andReturn([]);

    $synced = resolve(UpdateUserProfile::class)->handle($user);

    expect($synced)->toBeTrue()
        ->and($user->refresh())
        ->handle->toBe('octocat')
        ->name->toBe('The Octocat')
        ->avatar_url->toBe('https://avatars.githubusercontent.com/u/583231?v=4')
        ->bio->toBe('I am the Octocat.')
        ->company->toBe('@github')
        ->location->toBe('San Francisco')
        ->website->toBe('https://github.blog')
        ->email->toBe('private@example.com')
        ->has_sponsors_listing->toBeTrue()
        ->github_synced_at->toDateTimeString()->toBe(now()->toDateTimeString());
});

it('queues a lookup of the organizations the synced bio mentions', function (bool $mentions): void {
    Queue::fake();

    $user = User::factory()->create(['github_id' => '583231']);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('account')->with($user->github_id)->andReturn(['login' => 'octocat', 'bio' => $mentions ? 'Building @laravel' : 'Building things', 'company' => null]);
    $github->shouldReceive('login')->with($user->github_id)->andReturn('octocat');
    $github->shouldReceive('sshKeys')->with('octocat')->andReturn([]);

    resolve(UpdateUserProfile::class)->handle($user);

    $mentions
        ? Queue::assertPushed(LookUpUserOrganizations::class, fn (LookUpUserOrganizations $job): bool => $job->user->is($user))
        : Queue::assertNotPushed(LookUpUserOrganizations::class);
})->with(['a mention' => true, 'no mention' => false]);

it('clears profile fields removed on github', function (): void {
    $user = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('account')->with($user->github_id)->andReturn([
        'login' => 'octocat',
        'name' => null,
        'avatar_url' => '',
        'bio' => null,
        'company' => null,
        'location' => '  ',
        'blog' => '',
    ]);
    $github->shouldReceive('login')->with($user->github_id)->andReturn('octocat');
    $github->shouldReceive('sshKeys')->with('octocat')->andReturn([]);

    resolve(UpdateUserProfile::class)->handle($user);

    expect($user->refresh())
        ->name->toBe('octocat')
        ->avatar_url->toBeNull()
        ->bio->toBeNull()
        ->company->toBeNull()
        ->location->toBeNull()
        ->website->toBeNull();
});

it('mirrors the ssh keys of the user', function (): void {
    $user = User::factory()->create(['github_id' => '583231']);
    $removed = SshKey::factory()->for($user)->create();
    $publicKey = SshKeyPair::generate()->publicKey;

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('account')->with('583231')->andReturn(['id' => 583231, 'login' => 'octocat']);
    $github->shouldReceive('login')->with($user->github_id)->andReturn('octocat');
    $github->shouldReceive('sshKeys')->with('octocat')->andReturn([$publicKey->toOpenSsh()]);

    resolve(UpdateUserProfile::class)->handle($user);

    expect($removed->fresh())->toBeNull()
        ->and($user->sshKeys()->sole()->fingerprint)->toBe($publicKey->fingerprint());
});

it('reads the readme from github again, so an edited one shows after syncing', function (): void {
    $user = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('account')->with($user->github_id)->andReturn(['login' => 'octocat']);
    $github->shouldReceive('login')->with($user->github_id)->andReturn('octocat');
    $github->shouldReceive('sshKeys')->with('octocat')->andReturn([]);
    $github->shouldReceive('readme')->with('octocat')->andReturn('<p>Old</p>', '<p>New</p>');

    expect(resolve(GitHubReadmeQuery::class)->get($user))->toBe('<p>Old</p>');

    resolve(UpdateUserProfile::class)->handle($user);

    expect(resolve(GitHubReadmeQuery::class)->get($user))->toBe('<p>New</p>');
});

it('keeps the cached readme when the sync fails', function (): void {
    $user = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn('octocat');
    $github->shouldReceive('account')->with($user->github_id)->andThrow(new ConnectionException());
    $github->shouldReceive('readme')->with('octocat')->andReturn('<p>Old</p>', '<p>New</p>');

    expect(resolve(GitHubReadmeQuery::class)->get($user))->toBe('<p>Old</p>');

    resolve(UpdateUserProfile::class)->handle($user);

    expect(resolve(GitHubReadmeQuery::class)->get($user))->toBe('<p>Old</p>');
});

it('looks the account up by its github id, so a renamed handle cannot be claimed', function (): void {
    $user = User::factory()->create(['github_id' => '583231', 'handle' => 'old-handle']);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('account')->with($user->github_id)->andReturn(['login' => 'octocat']);
    $github->shouldReceive('login')->with($user->github_id)->andReturn('octocat');
    $github->shouldReceive('sshKeys')->with('octocat')->andReturn([]);

    resolve(UpdateUserProfile::class)->handle($user);

    $github->shouldHaveReceived('account')->with('583231');
    $github->shouldNotHaveReceived('accountByLogin');
    $github->shouldNotHaveReceived('sshKeys', ['old-handle']);
});

it('leaves the profile and ssh keys untouched when github fails', function (bool $gone): void {
    $user = User::factory()->create(['handle' => 'old-handle', 'bio' => 'Old bio']);
    $sshKey = SshKey::factory()->for($user)->create();

    $github = $this->mock(GitHubProvider::class);
    $gone
        ? $github->shouldReceive('account')->with($user->github_id)->andReturnNull()
        : $github->shouldReceive('account')->with($user->github_id)->andThrow(new RequestException(new Response(new Psr7Response(500))));

    $synced = resolve(UpdateUserProfile::class)->handle($user);

    expect($synced)->toBeFalse()
        ->and($user->refresh())
        ->handle->toBe('old-handle')
        ->bio->toBe('Old bio')
        ->github_synced_at->toBeNull()
        ->and($sshKey->fresh())->not->toBeNull();
})->with(['a gone account' => true, 'an error' => false]);

it('leaves the profile untouched when the ssh keys fail to sync', function (): void {
    $stale = User::factory()->create(['github_id' => '1', 'handle' => 'octocat']);
    $user = User::factory()->create(['github_id' => '583231', 'handle' => 'old-handle']);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('account')->with('583231')->andReturn(['id' => 583231, 'login' => 'octocat']);
    $github->shouldReceive('login')->with('583231')->andReturn('octocat');
    $github->shouldReceive('sshKeys')->with('octocat')->andThrow(new ConnectionException());

    $synced = resolve(UpdateUserProfile::class)->handle($user);

    expect($synced)->toBeFalse()
        ->and($user->refresh())
        ->handle->toBe('old-handle')
        ->github_synced_at->toBeNull()
        ->and($stale->refresh()->handle)->toBe('octocat');
});

it('fails when github cannot be reached', function (): void {
    $user = User::factory()->create(['handle' => 'old-handle']);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('account')->with($user->github_id)->andThrow(new ConnectionException());

    $synced = resolve(UpdateUserProfile::class)->handle($user);

    expect($synced)->toBeFalse()
        ->and($user->refresh()->handle)->toBe('old-handle');
});

it('frees the stale handle when syncing a renamed github account', function (string $staleUsername): void {
    $stale = User::factory()->create(['github_id' => '1', 'handle' => $staleUsername, 'bio' => 'Renamed away']);
    $user = User::factory()->create(['github_id' => '583231', 'handle' => 'old-handle', 'email' => 'private@example.com']);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('account')->with($user->github_id)->andReturn(['login' => 'octocat']);
    $github->shouldReceive('login')->with($user->github_id)->andReturn('octocat');
    $github->shouldReceive('sshKeys')->with('octocat')->andReturn([]);

    $synced = resolve(UpdateUserProfile::class)->handle($user);

    expect($synced)->toBeTrue()
        ->and($user->refresh())
        ->handle->toBe('octocat')
        ->github_id->toBe('583231')
        ->email->toBe('private@example.com')
        ->github_synced_at->toDateTimeString()->toBe(now()->toDateTimeString());

    expect($stale->refresh())
        ->handle->toBe('_renamed-1')
        ->github_id->toBe('1')
        ->bio->toBe('Renamed away')
        ->and(User::query()->count())->toBe(2);
})->with(['octocat', 'OctoCat']);
