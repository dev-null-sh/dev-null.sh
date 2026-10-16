<?php

declare(strict_types=1);

use App\Models\User;

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
