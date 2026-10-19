<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\Device;
use App\Models\Notification;
use App\Models\User;

it('returns the signed-in user with their unread notifications', function (): void {
    $user = User::factory()->create([
        'username' => 'octocat',
        'name' => 'The Octocat',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/583231',
        'bio' => 'I am the Octocat.',
        'company' => '@github',
        'location' => 'San Francisco',
        'website' => 'https://github.blog',
    ]);
    $keyPair = apiKeyPairFor($user);

    $user->following()->attach(User::factory()->count(2)->create());
    $user->followers()->attach(User::factory()->create());
    Commit::factory()->for($user)->pending()->create();
    Notification::factory()->for($user, 'recipient')->create();
    Notification::factory()->for($user, 'recipient')->read()->create();

    $response = signedApiRequest($keyPair, 'GET', route('api-current-user.show'));

    $response->assertOk()
        ->assertExactJson([
            'data' => [
                'id' => $user->id,
                'username' => 'octocat',
                'name' => 'The Octocat',
                'avatar_url' => 'https://avatars.githubusercontent.com/u/583231',
                'bio' => 'I am the Octocat.',
                'company' => '@github',
                'location' => 'San Francisco',
                'website' => 'https://github.blog',
                'github_url' => 'https://github.com/octocat',
                'html_url' => route('user.show', $user),
                'feed_url' => route('user-feed.show', $user),
                'created_at' => now()->toIso8601ZuluString(),
                'commits_count' => 1,
                'followers_count' => 3,
                'following_count' => 2,
                'lane' => $user->lane(),
            ],
            'unread_notifications_count' => 1,
        ]);
});

it('counts unread notifications in groups, like the web', function (): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);
    $commit = Commit::factory()->for($user)->create();

    Notification::factory()->for($user, 'recipient')->starred($commit)->count(3)->create();

    $response = signedApiRequest($keyPair, 'GET', route('api-current-user.show'));

    $response->assertOk()
        ->assertJsonPath('unread_notifications_count', 1);
});

it('rejects requests that are not signed', function (): void {
    $this->getJson(route('api-current-user.show'))->assertUnauthorized();
});

it('deletes the account of the signed-in user, with their commits and devices', function (): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);
    Device::factory()->for($user)->create();
    Commit::factory()->for($user)->create();
    Commit::factory()->for($user)->for(Commit::factory(), 'parent')->create();

    signedApiRequest($keyPair, 'DELETE', route('api-current-user.destroy'))->assertNoContent();

    expect($user->fresh())->toBeNull()
        ->and($user->commits()->withTrashed()->count())->toBe(0)
        ->and(Device::query()->count())->toBe(0);
});

it('stops the key of a deleted account from signing in', function (): void {
    $keyPair = apiKeyPairFor(User::factory()->create());

    signedApiRequest($keyPair, 'DELETE', route('api-current-user.destroy'))->assertNoContent();

    signedApiRequest($keyPair, 'GET', route('api-current-user.show'))
        ->assertUnauthorized()
        ->assertJsonPath('code', 'device_not_approved');
});

it('leaves other users alone when deleting an account', function (): void {
    $other = User::factory()->create();
    apiKeyPairFor($other);
    $commit = Commit::factory()->for($other)->create();

    signedApiRequest(apiKeyPairFor(User::factory()->create()), 'DELETE', route('api-current-user.destroy'))->assertNoContent();

    expect($other->fresh())->not->toBeNull()
        ->and($commit->fresh())->not->toBeNull()
        ->and($other->devices()->count())->toBe(1);
});

it('rejects deleting an account without a signed request', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->deleteJson(route('api-current-user.destroy'))->assertUnauthorized();

    expect($user->fresh())->not->toBeNull();
});
