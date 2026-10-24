<?php

declare(strict_types=1);

use App\Models\User;

it('finds people for guests, best matches first, as profiles with their links', function (): void {
    User::factory()->create(['username' => 'taylorotwell', 'name' => 'Taylor Otwell']);
    $exact = User::factory()->create(['username' => 'taylor', 'name' => 'Someone Else']);
    User::factory()->create(['username' => 'nuno', 'name' => 'Nuno Maduro']);

    $this->getJson('/api/v1/search/users?q=@Taylor')
        ->assertOk()
        ->assertJsonPath('data.*.username', ['taylor', 'taylorotwell'])
        ->assertJsonPath('data.0.id', $exact->id)
        ->assertJsonPath('data.0.html_url', route('user.show', $exact))
        ->assertJsonMissingPath('data.0.email')
        ->assertJsonMissingPath('data.0.lane');
});

it('leaves out the people a signed request has muted or is blocked with', function (): void {
    $viewer = User::factory()->create(['username' => 'viewer']);
    $keyPair = apiKeyPairFor($viewer);
    $muted = User::factory()->create(['username' => 'devmuted']);
    $blocked = User::factory()->create(['username' => 'devblocked']);
    $blocker = User::factory()->create(['username' => 'devblocker']);
    $visible = User::factory()->create(['username' => 'devvisible']);
    $viewer->muting()->attach($muted);
    $viewer->blocking()->attach($blocked);
    $blocker->blocking()->attach($viewer);

    signedApiRequest($keyPair, 'GET', route('api-user-search.index', ['q' => 'dev']))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$visible->id]);
});

it('returns at most 8 people, and nobody for a blank query', function (): void {
    User::factory()->count(12)->sequence(fn ($sequence): array => ['username' => "dev{$sequence->index}"])->create();

    $this->getJson('/api/v1/search/users?q=dev')->assertOk()->assertJsonCount(8, 'data');
    $this->getJson('/api/v1/search/users?q=%20')->assertOk()->assertJsonCount(0, 'data');
});

it('rejects a query over 100 characters', function (): void {
    $this->getJson('/api/v1/search/users?q='.str_repeat('a', 101))->assertUnprocessable();
});
