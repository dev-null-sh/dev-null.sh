<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('lists visible replies oldest first with their own reply counts', function (): void {
    $viewer = User::factory()->create();
    $parent = Commit::factory()->create();

    $second = Commit::factory()->for($parent, 'parent')->create(['body' => 'Second', 'created_at' => now()->subMinute()]);
    Commit::factory()->for($parent, 'parent')->create(['body' => 'First', 'created_at' => now()->subMinutes(2)]);
    Commit::factory()->for($parent, 'parent')->for($viewer)->pending()->create(['body' => 'Mine, pending']);
    Commit::factory()->for($parent, 'parent')->pending()->create(['body' => 'Hidden']);
    Commit::factory()->for($second, 'parent')->count(2)->create();
    Commit::factory()->for($second, 'parent')->pending()->create();

    $response = $this->actingAs($viewer)
        ->getJson(route('commit-reply.index', $parent));

    $response->assertOk()
        ->assertJsonCount(3, 'replies')
        ->assertJsonPath('replies.0.body', 'First')
        ->assertJsonPath('replies.1.body', 'Second')
        ->assertJsonPath('replies.1.replies_count', 2)
        ->assertJsonPath('replies.2.body', 'Mine, pending')
        ->assertJsonPath('replies.0.user.id', fn (string $id): bool => $id !== '')
        ->assertJsonMissingPath('replies.0.user.email');
});

it('does not list replies of a commit that is not visible', function (): void {
    $parent = Commit::factory()->pending()->create();

    $response = $this->actingAs(User::factory()->create())
        ->getJson(route('commit-reply.index', $parent));

    $response->assertNotFound();
});

it('requires authentication to list replies', function (): void {
    $response = $this->getJson(route('commit-reply.index', Commit::factory()->create()));

    $response->assertUnauthorized();
});
