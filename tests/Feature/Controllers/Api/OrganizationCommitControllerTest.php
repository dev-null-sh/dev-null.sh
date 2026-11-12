<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\Organization;
use App\Models\User;

it('lists the public commits an organization posted, newest first, without its replies', function (): void {
    $laravel = Organization::factory()->create(['handle' => 'laravel']);
    $poster = User::factory()->create();

    $older = Commit::factory()->for($poster)->create(['author_id' => $laravel->id, 'created_at' => now()->subHour()]);
    $newer = Commit::factory()->for($poster)->create(['author_id' => $laravel->id]);
    Commit::factory()->for($poster)->for($newer, 'parent')->create(['author_id' => $laravel->id]);
    Commit::factory()->for($poster)->pending()->create(['author_id' => $laravel->id]);
    Commit::factory()->for($poster)->create(['author_id' => $laravel->id])->delete();
    Commit::factory()->for($poster)->create();
    Commit::factory()->for($poster)->create(['author_id' => Organization::factory()->create()->id]);

    $this->getJson('/api/v1/organizations/laravel/commits')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$newer->id, $older->id])
        ->assertJsonPath('data.0.author.type', 'organization')
        ->assertJsonPath('data.0.author.handle', 'laravel')
        ->assertJsonPath('meta.next_cursor', null);
});

it('lists for a signed request the commits of an organization as the web shows them to its user, its pending ones to its members only', function (): void {
    $admin = User::factory()->create();
    $teammate = User::factory()->create();
    $stranger = User::factory()->create();
    $laravel = Organization::factory()->create(['handle' => 'laravel']);
    $laravel->admins()->attach([$admin->id, $teammate->id]);

    $pending = Commit::factory()->for($admin)->pending()->create(['author_id' => $laravel->id]);
    $approved = Commit::factory()->for($admin)->create(['author_id' => $laravel->id, 'created_at' => now()->subHour()]);

    signedApiRequest(apiKeyPairFor($admin), 'GET', route('api-organization-commit.index', 'laravel'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$pending->id, $approved->id])
        ->assertJsonPath('data.0.status', 'pending');

    signedApiRequest(apiKeyPairFor($teammate), 'GET', route('api-organization-commit.index', 'laravel'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$pending->id, $approved->id]);

    signedApiRequest(apiKeyPairFor($stranger), 'GET', route('api-organization-commit.index', 'laravel'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$approved->id]);
});

it('does not find the commits of a handle that is no organization', function (): void {
    User::factory()->create(['handle' => 'octocat']);

    $this->getJson('/api/v1/organizations/octocat/commits')->assertNotFound();
});
