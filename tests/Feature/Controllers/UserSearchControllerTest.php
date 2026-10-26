<?php

declare(strict_types=1);

use App\Models\User;

it('finds users by handle or name, best matches first', function (): void {
    User::factory()->create(['handle' => 'taylorotwell', 'name' => 'Taylor Otwell']);
    User::factory()->create(['handle' => 'taylor', 'name' => 'Someone Else']);
    User::factory()->create(['handle' => 'jess', 'name' => 'Jess Taylor']);
    User::factory()->create(['handle' => 'nuno', 'name' => 'Nuno Maduro']);

    $response = $this->actingAs(User::factory()->create(['handle' => 'viewer', 'name' => 'Viewer']))
        ->getJson('/search/users?q=Taylor');

    $response->assertOk()
        ->assertJsonCount(3, 'users')
        ->assertJsonPath('users.0.handle', 'taylor')
        ->assertJsonPath('users.1.handle', 'taylorotwell')
        ->assertJsonPath('users.2.handle', 'jess')
        ->assertJsonMissingPath('users.0.email')
        ->assertJsonMissingPath('users.0.github_id');
});

it('ignores a leading @ in the query', function (): void {
    User::factory()->create(['handle' => 'nuno', 'name' => 'Nuno Maduro']);

    $response = $this->actingAs(User::factory()->create())
        ->getJson('/search/users?q=@nun');

    $response->assertOk()
        ->assertJsonPath('users.0.handle', 'nuno');
});

it('treats like wildcards literally', function (): void {
    User::factory()->create(['handle' => 'nuno', 'name' => 'Nuno Maduro']);

    $response = $this->actingAs(User::factory()->create(['handle' => 'viewer', 'name' => 'Viewer']))
        ->getJson('/search/users?q=%25');

    $response->assertOk()
        ->assertJsonCount(0, 'users');
});

it('matches literal wildcard characters in display names', function (string $name, string $query): void {
    $match = User::factory()->create(['handle' => 'matching', 'name' => $name]);
    User::factory()->create(['handle' => 'other', 'name' => 'JaneXDoe']);

    $this->actingAs(User::factory()->create(['handle' => 'viewer']))
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
    User::factory()->count(12)->sequence(fn ($sequence): array => ['handle' => "dev{$sequence->index}"])->create();

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
    User::factory()->create(['handle' => 'nunomaduro']);

    $response = $this->getJson('/search/users?q=nuno');

    $response->assertOk()
        ->assertJsonPath('users.0.handle', 'nunomaduro');
});

it('leaves out muted people and people across a block', function (): void {
    $viewer = User::factory()->create(['handle' => 'viewer', 'name' => 'Viewer']);
    $muted = User::factory()->create(['handle' => 'taylor_muted']);
    $blocked = User::factory()->create(['handle' => 'taylor_blocked']);
    $blocker = User::factory()->create(['handle' => 'taylor_blocker']);
    User::factory()->create(['handle' => 'taylor']);

    $viewer->muting()->attach($muted);
    $viewer->blocking()->attach($blocked);
    $blocker->blocking()->attach($viewer);

    $response = $this->actingAs($viewer)
        ->getJson('/search/users?q=taylor');

    $response->assertOk()
        ->assertJsonCount(1, 'users')
        ->assertJsonPath('users.0.handle', 'taylor');
});
