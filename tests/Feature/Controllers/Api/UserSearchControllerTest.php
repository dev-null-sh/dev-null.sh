<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create(['handle' => 'viewer', 'name' => 'Viewer']);
    $this->keyPair = apiKeyPairFor($this->user);
});

it('finds people by handle or name, best matches first', function (): void {
    User::factory()->create(['handle' => 'taylorotwell', 'name' => 'Taylor Otwell']);
    User::factory()->create(['handle' => 'taylor', 'name' => 'Someone Else']);
    User::factory()->create(['handle' => 'jess', 'name' => 'Jess Taylor']);
    User::factory()->create(['handle' => 'nuno', 'name' => 'Nuno Maduro']);

    $response = signedApiRequest($this->keyPair, 'GET', route('api-user-search.index', ['q' => 'Taylor']));

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.handle', 'taylor')
        ->assertJsonPath('data.1.handle', 'taylorotwell')
        ->assertJsonPath('data.2.handle', 'jess')
        ->assertJsonMissingPath('data.0.email')
        ->assertJsonMissingPath('data.0.github_id');
});

it('says whether the user follows each one and whether each one follows them', function (): void {
    $followed = User::factory()->create(['handle' => 'taylor_followed']);
    $follower = User::factory()->create(['handle' => 'taylor_follower']);
    $mutual = User::factory()->create(['handle' => 'taylor_mutual']);
    $stranger = User::factory()->create(['handle' => 'taylor_stranger']);

    $this->user->following()->attach([$followed->id, $mutual->id]);
    $follower->following()->attach($this->user);
    $mutual->following()->attach($this->user);

    $users = collect(signedApiRequest($this->keyPair, 'GET', route('api-user-search.index', ['q' => 'taylor_']))
        ->assertOk()
        ->json('data'))->keyBy('handle');

    expect($users)->toHaveCount(4)
        ->and($users['taylor_followed'])->toMatchArray(['is_followed' => true, 'follows_you' => false])
        ->and($users['taylor_follower'])->toMatchArray(['is_followed' => false, 'follows_you' => true])
        ->and($users['taylor_mutual'])->toMatchArray(['is_followed' => true, 'follows_you' => true])
        ->and($users['taylor_stranger'])->toMatchArray(['is_followed' => false, 'follows_you' => false]);
});

it('leaves out muted people and people across a block', function (): void {
    $muted = User::factory()->create(['handle' => 'taylor_muted']);
    $blocked = User::factory()->create(['handle' => 'taylor_blocked']);
    $blocker = User::factory()->create(['handle' => 'taylor_blocker']);
    User::factory()->create(['handle' => 'taylor']);

    $this->user->muting()->attach($muted);
    $this->user->blocking()->attach($blocked);
    $blocker->blocking()->attach($this->user);

    signedApiRequest($this->keyPair, 'GET', route('api-user-search.index', ['q' => 'taylor']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.handle', 'taylor');
});

it('finds no one without a query', function (array $query): void {
    User::factory()->create();

    signedApiRequest($this->keyPair, 'GET', route('api-user-search.index', $query))
        ->assertOk()
        ->assertExactJson(['data' => []]);
})->with([
    'no query' => [[]],
    'a blank query' => [['q' => ' ']],
]);

it('rejects overly long queries', function (): void {
    signedApiRequest($this->keyPair, 'GET', route('api-user-search.index', ['q' => str_repeat('a', 101)]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');
});

it('requires a signed request', function (): void {
    $this->getJson(route('api-user-search.index', ['q' => 'taylor']))->assertUnauthorized();
});
