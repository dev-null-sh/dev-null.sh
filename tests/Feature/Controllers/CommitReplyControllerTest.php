<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;

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
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.body', 'First')
        ->assertJsonPath('data.1.body', 'Second')
        ->assertJsonPath('data.1.replies_count', 2)
        ->assertJsonPath('data.2.body', 'Mine, pending')
        ->assertJsonPath('data.0.user.id', fn (string $id): bool => $id !== '')
        ->assertJsonMissingPath('data.0.user.email')
        ->assertJsonPath('next_cursor', null);
});

it('lists replies 50 at a time with a cursor to the next page', function (): void {
    $viewer = User::factory()->create();
    $parent = Commit::factory()->create();

    Commit::factory()->for($parent, 'parent')->count(52)
        ->state(new Sequence(fn (Sequence $sequence): array => [
            'body' => "Reply {$sequence->index}",
            'created_at' => now()->subMinutes(100 - $sequence->index),
        ]))
        ->create();

    $first = $this->actingAs($viewer)
        ->getJson(route('commit-reply.index', $parent));

    $first->assertOk()
        ->assertJsonCount(50, 'data')
        ->assertJsonPath('data.0.body', 'Reply 0')
        ->assertJsonPath('data.49.body', 'Reply 49')
        ->assertJsonPath('next_cursor', fn (string $cursor): bool => $cursor !== '');

    $second = $this->actingAs($viewer)
        ->getJson(route('commit-reply.index', [$parent, 'cursor' => $first->json('next_cursor')]));

    $second->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.body', 'Reply 50')
        ->assertJsonPath('data.1.body', 'Reply 51')
        ->assertJsonPath('next_cursor', null);
});

it('orders replies created at the same time by id across pages', function (): void {
    $parent = Commit::factory()->create();
    $replies = Commit::factory()->for($parent, 'parent')->count(51)->create(['created_at' => now()->subMinute()]);

    $first = $this->actingAs(User::factory()->create())
        ->getJson(route('commit-reply.index', $parent));

    $second = $this->getJson(route('commit-reply.index', [$parent, 'cursor' => $first->json('next_cursor')]));

    expect([...$first->json('data.*.id'), ...$second->json('data.*.id')])
        ->toBe($replies->pluck('id')->sort()->values()->all());
});

it('only pages through replies visible to the viewer', function (): void {
    $viewer = User::factory()->create();
    $parent = Commit::factory()->create();

    Commit::factory()->for($parent, 'parent')->count(50)
        ->state(new Sequence(fn (Sequence $sequence): array => ['created_at' => now()->subMinutes(100 - $sequence->index)]))
        ->create();
    Commit::factory()->for($parent, 'parent')->pending()->count(3)->create(['created_at' => now()->subMinutes(10)]);
    Commit::factory()->for($parent, 'parent')->rejected()->create(['created_at' => now()->subMinutes(9)]);
    Commit::factory()->for($parent, 'parent')->for($viewer)->pending()->create(['body' => 'Mine, pending', 'created_at' => now()->subMinutes(8)]);

    $first = $this->actingAs($viewer)
        ->getJson(route('commit-reply.index', $parent));

    $first->assertJsonCount(50, 'data')
        ->assertJsonPath('data.*.status', array_fill(0, 50, 'approved'));

    $second = $this->getJson(route('commit-reply.index', [$parent, 'cursor' => $first->json('next_cursor')]));

    $second->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', 'Mine, pending')
        ->assertJsonPath('next_cursor', null);
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
