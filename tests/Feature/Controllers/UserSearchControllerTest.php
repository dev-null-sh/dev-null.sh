<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Models\User;
use App\Services\Features;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

it('finds users by username or name, best matches first', function (): void {
    User::factory()->create(['username' => 'taylorotwell', 'name' => 'Taylor Otwell']);
    User::factory()->create(['username' => 'taylor', 'name' => 'Someone Else']);
    User::factory()->create(['username' => 'jess', 'name' => 'Jess Taylor']);
    User::factory()->create(['username' => 'nuno', 'name' => 'Nuno Maduro']);

    $response = $this->actingAs(User::factory()->create(['username' => 'viewer', 'name' => 'Viewer']))
        ->getJson('/search/users?q=Taylor');

    $response->assertOk()
        ->assertJsonCount(3, 'users')
        ->assertJsonPath('users.0.username', 'taylor')
        ->assertJsonPath('users.1.username', 'taylorotwell')
        ->assertJsonPath('users.2.username', 'jess')
        ->assertJsonMissingPath('users.0.email')
        ->assertJsonMissingPath('users.0.github_id');
});

it('ignores a leading @ in the query', function (): void {
    User::factory()->create(['username' => 'nuno', 'name' => 'Nuno Maduro']);

    $response = $this->actingAs(User::factory()->create())
        ->getJson('/search/users?q=@nun');

    $response->assertOk()
        ->assertJsonPath('users.0.username', 'nuno');
});

it('treats like wildcards literally', function (): void {
    User::factory()->create(['username' => 'nuno', 'name' => 'Nuno Maduro']);

    $response = $this->actingAs(User::factory()->create(['username' => 'viewer', 'name' => 'Viewer']))
        ->getJson('/search/users?q=%25');

    $response->assertOk()
        ->assertJsonCount(0, 'users');
});

it('matches literal wildcard characters in display names', function (string $name, string $query): void {
    $match = User::factory()->create(['username' => 'matching', 'name' => $name]);
    User::factory()->create(['username' => 'other', 'name' => 'JaneXDoe']);

    $this->actingAs(User::factory()->create(['username' => 'viewer']))
        ->getJson('/search/users?q='.rawurlencode($query))
        ->assertOk()
        ->assertJsonCount(1, 'users')
        ->assertJsonPath('users.0.id', $match->id);
})->with([
    'underscore' => ['Jane_Doe', 'Jane_Doe'],
    'percent' => ['Discount 50%', '50%'],
    'escape character' => ['Bang!', 'Bang!'],
]);

it('returns nothing for an empty query', function (): void {
    User::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->getJson('/search/users?q=%20');

    $response->assertOk()
        ->assertJsonCount(0, 'users');
});

it('limits the number of results', function (): void {
    User::factory()->count(12)->sequence(fn ($sequence): array => ['username' => "dev{$sequence->index}"])->create();

    $response = $this->actingAs(User::factory()->create())
        ->getJson('/search/users?q=dev');

    $response->assertOk()
        ->assertJsonCount(8, 'users');
});

it('rejects overly long queries', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->getJson('/search/users?q='.str_repeat('a', 101));

    $response->assertUnprocessable();
});

it('lets guests search', function (): void {
    User::factory()->create(['username' => 'nunomaduro']);

    $response = $this->getJson('/search/users?q=nuno');

    $response->assertOk()
        ->assertJsonPath('users.0.username', 'nunomaduro');
});

it('leaves out muted people and people across a block', function (): void {
    $viewer = User::factory()->create(['username' => 'viewer', 'name' => 'Viewer']);
    $muted = User::factory()->create(['username' => 'taylor_muted']);
    $blocked = User::factory()->create(['username' => 'taylor_blocked']);
    $blocker = User::factory()->create(['username' => 'taylor_blocker']);
    User::factory()->create(['username' => 'taylor']);

    $viewer->muting()->attach($muted);
    $viewer->blocking()->attach($blocked);
    $blocker->blocking()->attach($viewer);

    $response = $this->actingAs($viewer)
        ->getJson('/search/users?q=taylor');

    $response->assertOk()
        ->assertJsonCount(1, 'users')
        ->assertJsonPath('users.0.username', 'taylor');
});

it('ranks users as Algolia does once its search key is set', function (): void {
    configureAlgolia();

    $first = User::factory()->create(['username' => 'nunomaduro']);
    $second = User::factory()->create(['username' => 'nuno']);

    Http::fake(['testapp-dsn.algolia.net/1/indexes/testing_users/query' => Http::response([
        'hits' => [['objectID' => $first->id], ['objectID' => Str::uuid7()->toString()], ['objectID' => $second->id]],
    ])]);

    $this->getJson('/search/users?q=@Nuno')
        ->assertOk()
        ->assertJsonCount(2, 'users')
        ->assertJsonPath('users.0.username', 'nunomaduro')
        ->assertJsonPath('users.1.username', 'nuno');

    Http::assertSent(fn (Request $request): bool => $request['query'] === 'nuno' && $request['hitsPerPage'] === 24);
});

it('skips Algolia hits whose object id is not a user id', function (): void {
    configureAlgolia();

    $user = User::factory()->create(['username' => 'nuno']);

    Http::fake(['testapp-dsn.algolia.net/1/indexes/testing_users/query' => Http::response([
        'hits' => [['objectID' => 'added-by-hand'], ['objectID' => $user->id]],
    ])]);

    $this->getJson('/search/users?q=nuno')
        ->assertOk()
        ->assertJsonCount(1, 'users')
        ->assertJsonPath('users.0.username', 'nuno');
});

it('leaves out muted people and people across a block from Algolia matches, up to the limit', function (): void {
    configureAlgolia();

    $viewer = User::factory()->create();
    $muted = User::factory()->create();
    $blocked = User::factory()->create();
    $reachable = User::factory()->count(9)->create();

    $viewer->muting()->attach($muted);
    $viewer->blocking()->attach($blocked);

    Http::fake(['testapp-dsn.algolia.net/*' => Http::response([
        'hits' => array_map(fn (string $id): array => ['objectID' => $id], [$muted->id, $blocked->id, ...$reachable->modelKeys()]),
    ])]);

    $this->actingAs($viewer)
        ->getJson('/search/users?q=dev')
        ->assertOk()
        ->assertJsonCount(8, 'users')
        ->assertJsonPath('users.0.id', $reachable->first()?->id);
});

it('reads further Algolia pages until enough reachable people are found', function (): void {
    configureAlgolia();

    $viewer = User::factory()->create();
    $muted = User::factory()->count(24)->create();
    $reachable = User::factory()->count(3)->create();

    $viewer->muting()->attach($muted);

    Http::fake(['testapp-dsn.algolia.net/*' => fn (Request $request) => Http::response([
        'hits' => array_map(fn (string $id): array => ['objectID' => $id], match ($request['page']) {
            0 => $muted->modelKeys(),
            1 => $reachable->modelKeys(),
            default => [],
        }),
    ])]);

    $this->actingAs($viewer)
        ->getJson('/search/users?q=dev')
        ->assertOk()
        ->assertJsonCount(3, 'users')
        ->assertJsonPath('users.0.id', $reachable->first()?->id);

    Http::assertSentCount(2);
});

it('stops reading Algolia pages once the limit is reached', function (): void {
    configureAlgolia();

    $reachable = User::factory()->count(24)->create();

    Http::fake(['testapp-dsn.algolia.net/*' => Http::response([
        'hits' => array_map(fn (string $id): array => ['objectID' => $id], $reachable->modelKeys()),
    ])]);

    $this->getJson('/search/users?q=dev')
        ->assertOk()
        ->assertJsonCount(8, 'users');

    Http::assertSentCount(1);
});

it('searches the database when Algolia fails', function (): void {
    configureAlgolia();
    Http::fake(['testapp-dsn.algolia.net/*' => Http::response([], 500)]);

    User::factory()->create(['username' => 'nuno']);

    $this->getJson('/search/users?q=nuno')
        ->assertOk()
        ->assertJsonPath('users.0.username', 'nuno');
});

it('searches the database while Algolia user search is switched off', function (): void {
    configureAlgolia();
    resolve(Features::class)->update(Feature::AlgoliaUserSearch, false);
    Http::fake();

    User::factory()->create(['username' => 'nuno']);

    $this->getJson('/search/users?q=nuno')
        ->assertOk()
        ->assertJsonPath('users.0.username', 'nuno');

    Http::assertNothingSent();
});
