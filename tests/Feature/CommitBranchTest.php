<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('stores the branch from the composer field and ignores body hashes and spoofed arrays', function (): void {
    $author = User::factory()->create();

    $this->actingAs($author)->post('/commits', [
        'body' => 'Shipped #Laravel with #PHP.',
        'branch' => 'Rust',
    ])->assertRedirect();

    expect($author->commits()->sole()->branch)->toBe('rust');
});

it('rejects an invalid branch name', function (): void {
    $author = User::factory()->create();

    $this->actingAs($author)->post('/commits', [
        'body' => 'No branch for this.',
        'branch' => '42',
    ])->assertSessionHasErrors('branch');

    expect($author->commits()->count())->toBe(0);
});

it('allows committing without a branch', function (): void {
    $author = User::factory()->create();

    $this->actingAs($author)->post('/commits', [
        'body' => 'Shipped without a branch.',
    ])->assertRedirect();

    expect($author->commits()->sole()->branch)->toBeNull();
});

it('shows a branch feed of matching top-level commits', function (): void {
    $this->withoutVite();

    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $match = Commit::factory()->for($author)->create([
        'body' => 'Hello',
        'branch' => 'laravel',
    ]);
    Commit::factory()->for($author)->create([
        'body' => 'Hello',
        'branch' => 'php',
    ]);
    Commit::factory()->for($author)->create([
        'body' => 'Reply',
        'branch' => 'laravel',
        'parent_id' => $match->id,
    ]);
    Commit::factory()->for($author)->pending()->create([
        'body' => 'Pending',
        'branch' => 'laravel',
    ]);

    $this->actingAs($viewer)->get(route('branch.show', 'laravel'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('branch/show')
            ->where('branch.name', 'laravel')
            ->has('commits.data', 1)
            ->where('commits.data.0.id', $match->id));
});

it('redirects mixed-case branch urls and rejects invalid branches', function (): void {
    $this->withoutVite();

    $this->get(route('branch.show', 'Laravel'))
        ->assertRedirect(route('branch.show', 'laravel'));

    $this->get('/branches/42')->assertNotFound();
    $this->get('/branches/_private')->assertNotFound();
});
