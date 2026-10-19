<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('suggests hashtags that start with the typed prefix, most used first', function (): void {
    $author = User::factory()->create();

    Commit::factory()->for($author)->create(['body' => 'a', 'branch' => 'laravel']);
    Commit::factory()->for($author)->create(['body' => 'b', 'branch' => 'laravel']);
    Commit::factory()->for($author)->create(['body' => 'c', 'branch' => 'lando']);
    Commit::factory()->for($author)->create(['body' => 'd', 'branch' => 'rust']);

    $this->actingAs($author)->getJson('/search/hashtags?q=la')
        ->assertOk()
        ->assertJsonCount(2, 'hashtags')
        ->assertJsonPath('hashtags.0.name', 'laravel')
        ->assertJsonPath('hashtags.0.count', 2)
        ->assertJsonPath('hashtags.1.name', 'lando')
        ->assertJsonPath('hashtags.1.count', 1);
});

it('prefers an exact match over a more popular longer tag', function (): void {
    $author = User::factory()->create();

    Commit::factory()->for($author)->count(3)->create(['branch' => 'laravel']);
    Commit::factory()->for($author)->create(['branch' => 'lara']);

    $this->actingAs($author)->getJson('/search/hashtags?q=lara')
        ->assertOk()
        ->assertJsonPath('hashtags.0.name', 'lara')
        ->assertJsonPath('hashtags.1.name', 'laravel');
});

it('ignores pending commits, replies and tags that do not match the prefix', function (): void {
    $author = User::factory()->create();
    $root = Commit::factory()->for($author)->create(['branch' => 'laravel']);

    Commit::factory()->for($author)->pending()->create(['branch' => 'laravel']);
    Commit::factory()->for($author)->create([
        'parent_id' => $root->id,
        'branch' => 'laravel',
    ]);
    Commit::factory()->for($author)->create(['branch' => 'php']);

    $this->actingAs($author)->getJson('/search/hashtags?q=la')
        ->assertOk()
        ->assertJsonCount(1, 'hashtags')
        ->assertJsonPath('hashtags.0.name', 'laravel')
        ->assertJsonPath('hashtags.0.count', 1);
});

it('requires at least two characters', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson('/search/hashtags?q=l')
        ->assertUnprocessable();
});

it('escapes like wildcards in the prefix', function (): void {
    $author = User::factory()->create();

    Commit::factory()->for($author)->create(['branch' => 'hello_world']);
    Commit::factory()->for($author)->create(['branch' => 'helloworld']);

    $this->actingAs($author)->getJson('/search/hashtags?q='.rawurlencode('hello_'))
        ->assertOk()
        ->assertJsonCount(1, 'hashtags')
        ->assertJsonPath('hashtags.0.name', 'hello_world');
});

it('limits the number of suggestions', function (): void {
    $author = User::factory()->create();

    foreach (range(0, 9) as $index) {
        Commit::factory()->for($author)->create(['branch' => "laravel{$index}"]);
    }

    $this->actingAs($author)->getJson('/search/hashtags?q=la')
        ->assertOk()
        ->assertJsonCount(6, 'hashtags');
});

it('requires authentication', function (): void {
    $this->getJson('/search/hashtags?q=la')->assertUnauthorized();
});
