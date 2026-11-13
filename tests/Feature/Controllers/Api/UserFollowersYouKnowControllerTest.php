<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('lists the followers you know only to signed requests, like the web to members', function (): void {
    User::factory()->create(['handle' => 'octocat']);

    $this->getJson('/api/v1/users/octocat/followers-you-know')->assertUnauthorized();
});

it('lists the followers of a user whom the signed-in user follows, newest follow first', function (): void {
    $viewer = User::factory()->create();
    $user = User::factory()->create();
    User::factory()->create(['handle' => 'TaylorOtwell']);
    $earlier = User::factory()->create(['bio' => 'Thanks @taylorotwell']);
    $later = User::factory()->create(['bio' => 'Ships on Fridays.', 'company' => null]);
    $user->followers()->attach($earlier, ['created_at' => now()->subMinute()]);
    $user->followers()->attach($later);
    $user->followers()->attach(User::factory()->create());
    $viewer->following()->attach([$earlier->id, $later->id, User::factory()->create()->id]);
    $viewer->followers()->attach($later);

    $response = signedApiRequest(apiKeyPairFor($viewer), 'GET', route('api-user-followers-you-know.index', $user))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$later->id, $earlier->id])
        ->assertJsonPath('data.*.is_followed', [true, true])
        ->assertJsonPath('data.*.follows_you', [true, false])
        ->assertJsonPath('data.1.bio_profiles', ['taylorotwell' => 'TaylorOtwell'])
        ->assertJsonMissingPath('data.0.email')
        ->assertJsonPath('meta.next_cursor', null);

    expect(json_decode($response->getContent(), flags: JSON_THROW_ON_ERROR)->data[0]->bio_profiles)->toEqual(new stdClass());
});

it('pages through the followers you know 30 at a time with a cursor, by id within the same moment', function (): void {
    $this->freezeTime();
    $viewer = User::factory()->create();
    $keyPair = apiKeyPairFor($viewer);
    $user = User::factory()->create();
    $known = User::factory()->count(31)->create();

    foreach ($known as $follower) {
        $user->followers()->attach($follower);
    }

    $viewer->following()->attach($known);

    $first = signedApiRequest($keyPair, 'GET', route('api-user-followers-you-know.index', $user))
        ->assertOk()
        ->assertJsonCount(30, 'data')
        ->assertJsonPath('meta.per_page', 30);

    signedApiRequest($keyPair, 'GET', $first->json('links.next'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$known->sortByDesc('id')->last()->id])
        ->assertJsonPath('meta.next_cursor', null);
});

it('loads a page of the followers you know without a query per follower', function (): void {
    $viewer = User::factory()->create();
    $keyPair = apiKeyPairFor($viewer);
    $user = User::factory()->create();
    User::factory()->create(['handle' => 'taylorotwell']);

    $queries = function () use ($keyPair, $user): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        signedApiRequest($keyPair, 'GET', route('api-user-followers-you-know.index', $user))->assertOk();

        return count(DB::getQueryLog());
    };

    // The first signed request also syncs the user's keys with GitHub...
    $queries();

    $follower = User::factory()->create(['bio' => 'Thanks @taylorotwell']);
    $user->followers()->attach($follower);
    $viewer->following()->attach($follower);
    $withOne = $queries();

    $more = User::factory()->count(5)->create(['bio' => 'Thanks @taylorotwell']);
    $user->followers()->attach($more);
    $viewer->following()->attach($more);

    expect($queries())->toBe($withOne);
});

it('lists no followers you know across a block for a signed request', function (Closure $block, string $code, bool $showsUser): void {
    $viewer = User::factory()->create();
    $user = User::factory()->create();

    $block($viewer, $user);

    signedApiRequest(apiKeyPairFor($viewer), 'GET', route('api-user-followers-you-know.index', $user))
        ->assertForbidden()
        ->assertJsonPath('code', $code)
        ->assertJsonPath('user.id', $showsUser ? $user->id : null)
        ->assertJsonMissingPath('data');
})->with([
    'blocking' => [fn (User $viewer, User $user) => $viewer->blocking()->attach($user), 'user_blocked', true],
    'blocked' => [fn (User $viewer, User $user) => $user->blocking()->attach($viewer), 'user_unavailable', false],
]);

it('returns not found for unknown handles', function (): void {
    signedApiRequest(apiKeyPairFor(User::factory()->create()), 'GET', '/api/v1/users/missing/followers-you-know')->assertNotFound();
});
