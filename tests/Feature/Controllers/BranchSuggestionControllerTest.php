<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Commit;
use App\Models\User;

it('suggests branches that start with the typed prefix, most used first', function (): void {
    $author = User::factory()->create();

    Commit::factory()->for($author)->create(['body' => 'a', 'branch' => 'laravel']);
    Commit::factory()->for($author)->create(['body' => 'b', 'branch' => 'laravel']);
    Commit::factory()->for($author)->create(['body' => 'c', 'branch' => 'lando']);
    Commit::factory()->for($author)->create(['body' => 'd', 'branch' => 'rust']);

    $this->actingAs($author)->getJson('/search/branches?q=la')
        ->assertOk()
        ->assertJsonCount(2, 'branches')
        ->assertJsonPath('branches.0.name', 'laravel')
        ->assertJsonPath('branches.0.count', 2)
        ->assertJsonPath('branches.1.name', 'lando')
        ->assertJsonPath('branches.1.count', 1);
});

it('lists owned branches for discovery without inflating their commit counts', function (): void {
    $author = User::factory()->create();

    Branch::factory()->create(['name' => 'laravel']);
    Commit::factory()->for($author)->create(['branch' => 'laravel']);

    $this->actingAs($author)->getJson('/search/branches?q=la')
        ->assertOk()
        ->assertJsonPath('branches.0.name', 'laravel')
        ->assertJsonPath('branches.0.count', 1)
        ->assertJsonPath('branches.0.is_verified', false)
        ->assertJsonPath('branches.0.verified_domain', null);
});

it('includes verification and domain for verified branch suggestions', function (): void {
    $author = User::factory()->create();

    Branch::factory()->verified('laravel.com')->create(['name' => 'laravel']);
    Commit::factory()->for($author)->count(2)->create(['branch' => 'laravel']);
    Commit::factory()->for($author)->create(['branch' => 'lando']);

    $response = $this->actingAs($author)->getJson('/search/branches?q=la')->assertOk();

    expect($response->json('branches.0'))->toMatchArray([
        'name' => 'laravel',
        'count' => 2,
        'is_verified' => true,
        'verified_domain' => 'laravel.com',
    ])->and($response->json('branches.1'))->toMatchArray([
        'name' => 'lando',
        'count' => 1,
        'is_verified' => false,
        'verified_domain' => null,
    ]);
});

it('prefers an exact match over a more popular longer branch', function (): void {
    $author = User::factory()->create();

    Commit::factory()->for($author)->count(3)->create(['branch' => 'laravel']);
    Commit::factory()->for($author)->create(['branch' => 'lara']);

    $this->actingAs($author)->getJson('/search/branches?q=lara')
        ->assertOk()
        ->assertJsonPath('branches.0.name', 'lara')
        ->assertJsonPath('branches.1.name', 'laravel');
});

it('ignores pending commits, replies and branches that do not match the prefix', function (): void {
    $author = User::factory()->create();
    $root = Commit::factory()->for($author)->create(['branch' => 'laravel']);

    Commit::factory()->for($author)->pending()->create(['branch' => 'laravel']);
    Commit::factory()->for($author)->create([
        'parent_id' => $root->id,
        'branch' => 'laravel',
    ]);
    Commit::factory()->for($author)->create(['branch' => 'php']);

    $this->actingAs($author)->getJson('/search/branches?q=la')
        ->assertOk()
        ->assertJsonCount(1, 'branches')
        ->assertJsonPath('branches.0.name', 'laravel')
        ->assertJsonPath('branches.0.count', 1);
});

it('requires at least two characters', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson('/search/branches?q=l')
        ->assertUnprocessable();
});

it('escapes like wildcards in the prefix', function (): void {
    $author = User::factory()->create();

    Commit::factory()->for($author)->create(['branch' => 'hello_world']);
    Commit::factory()->for($author)->create(['branch' => 'helloworld']);

    $this->actingAs($author)->getJson('/search/branches?q='.rawurlencode('hello_'))
        ->assertOk()
        ->assertJsonCount(1, 'branches')
        ->assertJsonPath('branches.0.name', 'hello_world');
});

it('limits the number of suggestions', function (): void {
    $author = User::factory()->create();

    foreach (range(0, 9) as $index) {
        Commit::factory()->for($author)->create(['branch' => "laravel{$index}"]);
    }

    $this->actingAs($author)->getJson('/search/branches?q=la')
        ->assertOk()
        ->assertJsonCount(6, 'branches');
});

it('requires authentication', function (): void {
    $this->getJson('/search/branches?q=la')->assertUnauthorized();
});
