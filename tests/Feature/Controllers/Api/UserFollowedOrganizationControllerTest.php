<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

it('lists the organizations a user follows only to signed requests, like the web to members', function (): void {
    $user = User::factory()->create(['handle' => 'octocat']);
    $user->followedOrganizations()->attach(Organization::factory()->create());

    $this->getJson('/api/v1/users/octocat/following/organizations')->assertUnauthorized();
});

it('lists the organizations a user follows, newest follow first, with whether the signed-in user follows each one', function (): void {
    $viewer = User::factory()->create();
    $keyPair = apiKeyPairFor($viewer);
    $user = User::factory()->create();
    $laravel = Organization::factory()->create(['handle' => 'laravel']);
    $bref = Organization::factory()->create(['handle' => 'brefphp']);
    $user->followedOrganizations()->attach($bref, ['created_at' => now()->subMinute()]);
    $user->followedOrganizations()->attach($laravel);
    $user->following()->attach(User::factory()->create());
    $viewer->followedOrganizations()->attach($bref);

    signedApiRequest($keyPair, 'GET', route('api-user-followed-organization.index', $user))
        ->assertOk()
        ->assertJsonPath('data.*.handle', ['laravel', 'brefphp'])
        ->assertJsonPath('data.0.html_url', route('user.show', ['user' => 'laravel']))
        ->assertJsonPath('data.*.is_followed', [false, true])
        ->assertJsonPath('meta.next_cursor', null);
});

it('pages through the organizations 30 at a time', function (): void {
    $user = User::factory()->create();
    $organizations = Organization::factory()->count(31)->create();

    foreach ($organizations as $index => $organization) {
        $user->followedOrganizations()->attach($organization, ['created_at' => now()->subMinutes($index)]);
    }

    $keyPair = apiKeyPairFor(User::factory()->create());

    $first = signedApiRequest($keyPair, 'GET', route('api-user-followed-organization.index', $user))
        ->assertOk()
        ->assertJsonCount(30, 'data');

    signedApiRequest($keyPair, 'GET', $first->json('links.next'))
        ->assertOk()
        ->assertJsonPath('data.*.handle', [$organizations->last()->handle]);
});

it('lists none across a block for a signed request', function (Closure $block, string $code): void {
    $viewer = User::factory()->create();
    $keyPair = apiKeyPairFor($viewer);
    $user = User::factory()->create();
    $user->followedOrganizations()->attach(Organization::factory()->create());

    $block($viewer, $user);

    signedApiRequest($keyPair, 'GET', route('api-user-followed-organization.index', $user))
        ->assertForbidden()
        ->assertJsonPath('code', $code)
        ->assertJsonMissingPath('data');
})->with([
    'blocking' => [fn (User $viewer, User $user) => $viewer->blocking()->attach($user), 'user_blocked'],
    'blocked' => [fn (User $viewer, User $user) => $user->blocking()->attach($viewer), 'user_unavailable'],
]);
