<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('lists the followers of a user to guests, newest follow first', function (): void {
    $user = User::factory()->create(['handle' => 'octocat']);
    $earlier = User::factory()->create(['bio' => 'Ships on Fridays.']);
    $later = User::factory()->create();
    $user->followers()->attach($earlier, ['created_at' => now()->subMinute()]);
    $user->followers()->attach($later);
    $user->following()->attach(User::factory()->create());

    $this->getJson('/api/v1/users/octocat/followers')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$later->id, $earlier->id])
        ->assertJsonPath('data.1.handle', $earlier->handle)
        ->assertJsonPath('data.1.bio', 'Ships on Fridays.')
        ->assertJsonMissingPath('data.0.lane')
        ->assertJsonMissingPath('data.0.is_followed')
        ->assertJsonMissingPath('data.0.follows_you')
        ->assertJsonMissingPath('data.0.email')
        ->assertJsonPath('meta.next_cursor', null);
});

it('says for a signed request whether its user follows each follower, and whether each follows them', function (): void {
    $viewer = User::factory()->create();
    $keyPair = apiKeyPairFor($viewer);
    $user = User::factory()->create();
    $mutual = User::factory()->create();
    $followed = User::factory()->create();
    $stranger = User::factory()->create();
    $user->followers()->attach($stranger, ['created_at' => now()->subMinutes(2)]);
    $user->followers()->attach($followed, ['created_at' => now()->subMinute()]);
    $user->followers()->attach($mutual);
    $viewer->following()->attach([$followed->id, $mutual->id]);
    $viewer->followers()->attach($mutual);

    signedApiRequest($keyPair, 'GET', route('api-user-followers.index', $user))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$mutual->id, $followed->id, $stranger->id])
        ->assertJsonPath('data.*.is_followed', [true, true, false])
        ->assertJsonPath('data.*.follows_you', [true, false, false])
        ->assertJsonPath('data.0.lane', $mutual->lane());
});

it('lists the signed-in user among the followers, following nobody', function (): void {
    $viewer = User::factory()->create();
    $keyPair = apiKeyPairFor($viewer);
    $user = User::factory()->create();
    $user->followers()->attach($viewer);

    signedApiRequest($keyPair, 'GET', route('api-user-followers.index', $user))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$viewer->id])
        ->assertJsonPath('data.0.is_followed', false)
        ->assertJsonPath('data.0.follows_you', false);
});

it('pages through followers 30 at a time with a cursor', function (): void {
    $keyPair = apiKeyPairFor(User::factory()->create());
    $user = User::factory()->create();
    $followers = User::factory()->count(31)->create();

    foreach ($followers as $index => $follower) {
        $user->followers()->attach($follower, ['created_at' => now()->subMinutes($index)]);
    }

    $first = signedApiRequest($keyPair, 'GET', route('api-user-followers.index', $user))
        ->assertOk()
        ->assertJsonCount(30, 'data')
        ->assertJsonPath('meta.per_page', 30);

    expect($first->json('meta.next_cursor'))->toBeString();

    signedApiRequest($keyPair, 'GET', $first->json('links.next'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$followers->last()->id])
        ->assertJsonPath('meta.next_cursor', null);
});

it('loads a page of followers without a query per follower', function (): void {
    $keyPair = apiKeyPairFor(User::factory()->create());
    $user = User::factory()->create();

    $queries = function () use ($keyPair, $user): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        signedApiRequest($keyPair, 'GET', route('api-user-followers.index', $user))->assertOk();

        return count(DB::getQueryLog());
    };

    // The first signed request also syncs the user's keys with GitHub...
    $queries();

    $user->followers()->attach(User::factory()->create());
    $withOne = $queries();

    $user->followers()->attach(User::factory()->count(5)->create());

    expect($queries())->toBe($withOne);
});

it('lists no followers of a profile across a block for a signed request', function (Closure $block, string $code, bool $showsUser): void {
    $viewer = User::factory()->create();
    $keyPair = apiKeyPairFor($viewer);
    $user = User::factory()->create();
    $user->followers()->attach(User::factory()->create());

    $block($viewer, $user);

    signedApiRequest($keyPair, 'GET', route('api-user-followers.index', $user))
        ->assertForbidden()
        ->assertJsonPath('code', $code)
        // Only the one who blocked is told who, for the blocked page.
        ->assertJsonPath('user.id', $showsUser ? $user->id : null)
        ->assertJsonMissingPath('data');

    $this->getJson(route('api-user-followers.index', $user))
        ->assertOk()
        ->assertJsonCount(1, 'data');
})->with([
    'blocking' => [fn (User $viewer, User $user) => $viewer->blocking()->attach($user), 'user_blocked', true],
    'blocked' => [fn (User $viewer, User $user) => $user->blocking()->attach($viewer), 'user_unavailable', false],
]);

it('returns not found for unknown handles', function (): void {
    $this->getJson('/api/v1/users/missing/followers')->assertNotFound();
});
