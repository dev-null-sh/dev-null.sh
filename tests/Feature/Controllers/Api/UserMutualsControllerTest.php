<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('lists the mutuals of a user only to signed requests, like the web to members', function (): void {
    User::factory()->create(['handle' => 'octocat']);

    $this->getJson('/api/v1/users/octocat/mutuals')->assertUnauthorized();
});

it('lists the people a user follows who follow them back, newest follow first', function (): void {
    $viewer = User::factory()->create();
    $user = User::factory()->create();
    User::factory()->create(['handle' => 'TaylorOtwell']);
    $earlier = User::factory()->create(['bio' => 'Thanks @taylorotwell']);
    $later = User::factory()->create(['bio' => 'Ships on Fridays.', 'company' => null]);
    $user->following()->attach($earlier, ['created_at' => now()->subMinute()]);
    $user->following()->attach($later);
    $user->followers()->attach([$earlier->id, $later->id]);
    $user->following()->attach(User::factory()->create());
    $user->followers()->attach(User::factory()->create());
    $viewer->following()->attach($earlier);
    $viewer->followers()->attach($later);

    $response = signedApiRequest(apiKeyPairFor($viewer), 'GET', route('api-user-mutuals.index', $user))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$later->id, $earlier->id])
        ->assertJsonPath('data.*.is_followed', [false, true])
        ->assertJsonPath('data.*.follows_you', [true, false])
        ->assertJsonPath('data.1.bio_profiles', ['taylorotwell' => 'TaylorOtwell'])
        ->assertJsonMissingPath('data.0.email')
        ->assertJsonPath('meta.next_cursor', null);

    expect(json_decode($response->getContent(), flags: JSON_THROW_ON_ERROR)->data[0]->bio_profiles)->toEqual(new stdClass());
});

it('pages through mutuals 30 at a time with a cursor, by id within the same moment', function (): void {
    $this->freezeTime();
    $keyPair = apiKeyPairFor(User::factory()->create());
    $user = User::factory()->create();
    $mutuals = User::factory()->count(31)->create();

    foreach ($mutuals as $mutual) {
        $user->following()->attach($mutual);
        $user->followers()->attach($mutual);
    }

    $first = signedApiRequest($keyPair, 'GET', route('api-user-mutuals.index', $user))
        ->assertOk()
        ->assertJsonCount(30, 'data')
        ->assertJsonPath('meta.per_page', 30);

    signedApiRequest($keyPair, 'GET', $first->json('links.next'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$mutuals->sortByDesc('id')->last()->id])
        ->assertJsonPath('meta.next_cursor', null);
});

it('loads a page of mutuals without a query per mutual', function (): void {
    $keyPair = apiKeyPairFor(User::factory()->create());
    $user = User::factory()->create();
    User::factory()->create(['handle' => 'taylorotwell']);

    $queries = function () use ($keyPair, $user): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        signedApiRequest($keyPair, 'GET', route('api-user-mutuals.index', $user))->assertOk();

        return count(DB::getQueryLog());
    };

    // The first signed request also syncs the user's keys with GitHub...
    $queries();

    $mutual = User::factory()->create(['bio' => 'Thanks @taylorotwell']);
    $user->following()->attach($mutual);
    $user->followers()->attach($mutual);
    $withOne = $queries();

    $more = User::factory()->count(5)->create(['bio' => 'Thanks @taylorotwell']);
    $user->following()->attach($more);
    $user->followers()->attach($more);

    expect($queries())->toBe($withOne);
});

it('lists no mutuals of a profile across a block for a signed request', function (Closure $block, string $code, bool $showsUser): void {
    $viewer = User::factory()->create();
    $user = User::factory()->create();

    $block($viewer, $user);

    signedApiRequest(apiKeyPairFor($viewer), 'GET', route('api-user-mutuals.index', $user))
        ->assertForbidden()
        ->assertJsonPath('code', $code)
        ->assertJsonPath('user.id', $showsUser ? $user->id : null)
        ->assertJsonMissingPath('data');
})->with([
    'blocking' => [fn (User $viewer, User $user) => $viewer->blocking()->attach($user), 'user_blocked', true],
    'blocked' => [fn (User $viewer, User $user) => $user->blocking()->attach($viewer), 'user_unavailable', false],
]);

it('returns not found for unknown handles', function (): void {
    signedApiRequest(apiKeyPairFor(User::factory()->create()), 'GET', '/api/v1/users/missing/mutuals')->assertNotFound();
});
