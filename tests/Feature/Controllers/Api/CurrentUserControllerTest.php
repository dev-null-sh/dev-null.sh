<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\Device;
use App\Models\Notification;
use App\Models\User;

it('returns the signed-in user with their unread notifications and their pastéis', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 17)->setTime(9, 15));

    $user = User::factory()->create([
        'username' => 'octocat',
        'name' => 'The Octocat',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/583231',
        'bio' => 'I am the Octocat.',
        'company' => '@github',
        'location' => 'San Francisco',
        'website' => 'https://github.blog',
        'pasteis_mode' => true,
        'pasteis_received' => 12,
        'pasteis_given_today' => 3,
        'pasteis_given_on' => today(),
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
                'pasteis_mode' => true,
                'pasteis_remaining' => 7,
                'pasteis_resets_at' => '2026-10-18T00:00:00Z',
                'pasteis_received' => 12,
                'preferred_lane' => null,
                'automatic_lane' => $user->automaticLane(),
                'sponsors_url' => null,
                'github_synced_at' => null,
                'remotes' => [
                    'from' => 'following',
                    'pull_receipts' => true,
                    'requests' => false,
                    'everyone' => false,
                    'unread_count' => 0,
                ],
            ],
            'unread_notifications_count' => 1,
        ]);
});

it('returns what the web shows only the user on their profile settings', function (): void {
    $user = User::factory()->create([
        'id' => '3a788e93-4cf7-4819-9377-94be2f329563',
        'username' => 'octocat',
        'preferred_lane' => 1,
        'has_sponsors_listing' => true,
        'github_synced_at' => '2026-10-01 09:30:00',
    ]);
    $keyPair = apiKeyPairFor($user);

    $response = signedApiRequest($keyPair, 'GET', route('api-current-user.show'));

    $response->assertOk()
        ->assertJsonPath('data.lane', 1)
        ->assertJsonPath('data.preferred_lane', 1)
        ->assertJsonPath('data.automatic_lane', 4)
        ->assertJsonPath('data.sponsors_url', 'https://github.com/sponsors/octocat')
        ->assertJsonPath('data.github_synced_at', '2026-10-01T09:30:00Z');
});

it('keeps those to the signed-in user, even on their own profile', function (): void {
    $user = User::factory()->create(['preferred_lane' => 1]);
    $keyPair = apiKeyPairFor($user);

    $response = signedApiRequest($keyPair, 'GET', route('api-user.show', $user));

    $response->assertOk()
        ->assertJsonPath('data.lane', 1)
        ->assertJsonMissingPath('data.preferred_lane')
        ->assertJsonMissingPath('data.automatic_lane')
        ->assertJsonMissingPath('data.sponsors_url')
        ->assertJsonMissingPath('data.github_synced_at');
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

it('counts unread messages and requests the way the nav on the web does', function (): void {
    config(['remotes.requests' => true]);

    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);
    $followed = User::factory()->create();
    $stranger = User::factory()->create();

    $user->following()->attach($followed);
    sendChatMessage($followed, $user);
    sendChatMessage($followed, $user, 'still there?');
    sendChatMessage($stranger, $user);

    signedApiRequest($keyPair, 'GET', route('api-current-user.show'))
        ->assertOk()
        ->assertJsonPath('data.remotes.requests', true)
        ->assertJsonPath('data.remotes.unread_count', 3);
});

it('says nothing about Remotes while the user has them off', function (): void {
    $user = User::factory()->create(['remotes_enabled' => false]);
    $keyPair = apiKeyPairFor($user);

    signedApiRequest($keyPair, 'GET', route('api-current-user.show'))
        ->assertOk()
        ->assertJsonPath('data.remotes', null);
});

it('rejects requests that are not signed', function (): void {
    $this->getJson(route('api-current-user.show'))->assertUnauthorized();
});

it('gives the user all their pastéis again once a new day starts in UTC, and what they were given with the mode off', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 17)->setTime(0, 0, 1));

    $user = User::factory()->create(['pasteis_mode' => false, 'pasteis_received' => 5, 'pasteis_given_today' => 10, 'pasteis_given_on' => '2026-10-16']);
    $keyPair = apiKeyPairFor($user);

    signedApiRequest($keyPair, 'GET', route('api-current-user.show'))
        ->assertOk()
        ->assertJsonPath('data.pasteis_mode', false)
        ->assertJsonPath('data.pasteis_remaining', User::DAILY_PASTEIS)
        ->assertJsonPath('data.pasteis_resets_at', '2026-10-18T00:00:00Z')
        ->assertJsonPath('data.pasteis_received', 5);
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
