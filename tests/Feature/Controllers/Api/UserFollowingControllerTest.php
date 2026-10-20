<?php

declare(strict_types=1);

use App\Models\User;

it('lists the people a user follows to guests, newest follow first', function (): void {
    $user = User::factory()->create(['username' => 'octocat']);
    $earlier = User::factory()->create();
    $later = User::factory()->create();
    $user->following()->attach($earlier, ['created_at' => now()->subMinute()]);
    $user->following()->attach($later);
    $user->followers()->attach(User::factory()->create());

    $this->getJson('/api/v1/users/octocat/following')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$later->id, $earlier->id])
        ->assertJsonMissingPath('data.0.lane')
        ->assertJsonMissingPath('data.0.is_followed')
        ->assertJsonMissingPath('data.0.follows_you')
        ->assertJsonPath('meta.next_cursor', null);
});

it('says for a signed request whether its user follows each one, and whether each follows them', function (): void {
    $viewer = User::factory()->create();
    $keyPair = apiKeyPairFor($viewer);
    $user = User::factory()->create();
    $mutual = User::factory()->create();
    $followed = User::factory()->create();
    $stranger = User::factory()->create();
    $user->following()->attach($stranger, ['created_at' => now()->subMinutes(2)]);
    $user->following()->attach($followed, ['created_at' => now()->subMinute()]);
    $user->following()->attach($mutual);
    $viewer->following()->attach([$followed->id, $mutual->id]);
    $viewer->followers()->attach($mutual);

    signedApiRequest($keyPair, 'GET', route('api-user-following.index', $user))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$mutual->id, $followed->id, $stranger->id])
        ->assertJsonPath('data.*.is_followed', [true, true, false])
        ->assertJsonPath('data.*.follows_you', [true, false, false])
        ->assertJsonPath('data.0.lane', $mutual->lane());
});

it('pages through the people a user follows 30 at a time', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->count(31)->create();

    foreach ($followed as $index => $person) {
        $user->following()->attach($person, ['created_at' => now()->subMinutes($index)]);
    }

    $first = $this->getJson(route('api-user-following.index', $user))
        ->assertOk()
        ->assertJsonCount(30, 'data');

    $this->getJson($first->json('links.next'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$followed->last()->id]);
});

it('lists no one a profile follows across a block for a signed request', function (Closure $block, string $code, bool $showsUser): void {
    $viewer = User::factory()->create();
    $keyPair = apiKeyPairFor($viewer);
    $user = User::factory()->create();
    $user->following()->attach(User::factory()->create());

    $block($viewer, $user);

    signedApiRequest($keyPair, 'GET', route('api-user-following.index', $user))
        ->assertForbidden()
        ->assertJsonPath('code', $code)
        // Only the one who blocked is told who, for the blocked page.
        ->assertJsonPath('user.id', $showsUser ? $user->id : null)
        ->assertJsonMissingPath('data');

    $this->getJson(route('api-user-following.index', $user))
        ->assertOk()
        ->assertJsonCount(1, 'data');
})->with([
    'blocking' => [fn (User $viewer, User $user) => $viewer->blocking()->attach($user), 'user_blocked', true],
    'blocked' => [fn (User $viewer, User $user) => $user->blocking()->attach($viewer), 'user_unavailable', false],
]);

it('returns not found for unknown usernames', function (): void {
    $this->getJson('/api/v1/users/missing/following')->assertNotFound();
});
