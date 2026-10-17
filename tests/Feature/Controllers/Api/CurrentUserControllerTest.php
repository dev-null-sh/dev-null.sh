<?php

declare(strict_types=1);

use App\Models\Commit;
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
