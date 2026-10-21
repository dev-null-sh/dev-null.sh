<?php

declare(strict_types=1);

use App\Models\User;

it('finds users by username or name, best matches first', function (): void {
    User::factory()->create(['username' => 'taylorotwell', 'name' => 'Taylor Otwell']);
    User::factory()->create(['username' => 'taylor', 'name' => 'Someone Else']);
    User::factory()->create(['username' => 'jess', 'name' => 'Jess Taylor']);
    User::factory()->create(['username' => 'nuno', 'name' => 'Nuno Maduro']);

    $this->getJson(route('api-user-search.index', ['q' => 'Taylor']))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.username', 'taylor')
        ->assertJsonPath('data.1.username', 'taylorotwell')
        ->assertJsonPath('data.2.username', 'jess')
        ->assertJsonMissingPath('data.0.email')
        ->assertJsonMissingPath('data.0.github_id')
        ->assertJsonMissingPath('data.0.lane')
        ->assertJsonMissingPath('data.0.is_followed')
        ->assertJsonMissingPath('users');
});

it('ignores a leading @ in the query', function (): void {
    User::factory()->create(['username' => 'nuno', 'name' => 'Nuno Maduro']);

    $this->getJson(route('api-user-search.index', ['q' => '@nun']))
        ->assertOk()
        ->assertJsonPath('data.0.username', 'nuno');
});

it('returns nothing for an empty query', function (): void {
    User::factory()->create();

    $this->getJson(route('api-user-search.index', ['q' => ' ']))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('limits the number of results', function (): void {
    User::factory()->count(12)->sequence(fn ($sequence): array => ['username' => "dev{$sequence->index}"])->create();

    $this->getJson(route('api-user-search.index', ['q' => 'dev']))
        ->assertOk()
        ->assertJsonCount(8, 'data');
});

it('rejects overly long queries', function (): void {
    $this->getJson(route('api-user-search.index', ['q' => str_repeat('a', 101)]))->assertUnprocessable();
});

it('says for a signed request whether its user follows each person, and whether each follows them', function (): void {
    $viewer = User::factory()->create();
    $mutual = User::factory()->create(['username' => 'taylor_mutual']);
    $followed = User::factory()->create(['username' => 'taylor_followed']);
    $stranger = User::factory()->create(['username' => 'taylor_stranger']);

    $viewer->following()->attach([$followed->id, $mutual->id]);
    $viewer->followers()->attach($mutual);

    signedApiRequest(apiKeyPairFor($viewer), 'GET', route('api-user-search.index', ['q' => 'taylor']))
        ->assertOk()
        ->assertJsonPath('data.*.username', ['taylor_followed', 'taylor_mutual', 'taylor_stranger'])
        ->assertJsonPath('data.*.is_followed', [true, true, false])
        ->assertJsonPath('data.*.follows_you', [false, true, false])
        ->assertJsonPath('data.0.lane', $followed->lane());
});

it('leaves out muted people and people across a block for a signed request', function (): void {
    $viewer = User::factory()->create(['username' => 'viewer', 'name' => 'Viewer']);
    $muted = User::factory()->create(['username' => 'taylor_muted']);
    $blocked = User::factory()->create(['username' => 'taylor_blocked']);
    $blocker = User::factory()->create(['username' => 'taylor_blocker']);
    User::factory()->create(['username' => 'taylor']);

    $viewer->muting()->attach($muted);
    $viewer->blocking()->attach($blocked);
    $blocker->blocking()->attach($viewer);

    signedApiRequest(apiKeyPairFor($viewer), 'GET', route('api-user-search.index', ['q' => 'taylor']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.username', 'taylor');
});
