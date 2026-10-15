<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;

test('to array', function (): void {
    $commit = Commit::factory()->create()->refresh();

    expect(array_keys($commit->toArray()))
        ->toBe([
            'id',
            'user_id',
            'body',
            'created_at',
            'updated_at',
            'status',
            'parent_id',
            'deleted_at',
        ]);
});

it('belongs to a user', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create();

    expect($commit->user->is($user))->toBeTrue();
});

it('casts the status to an enum', function (): void {
    $commit = Commit::factory()->pending()->create()->refresh();

    expect($commit->status)->toBe(CommitStatus::Pending);
});

it('may reply to another commit', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();

    expect($reply->parent->is($parent))->toBeTrue()
        ->and($parent->replies->sole()->is($reply))->toBeTrue();
});

it('keeps replies when their parent is deleted', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();

    $parent->delete();

    $this->assertSoftDeleted($parent);
    $this->assertNotSoftDeleted($reply);
});

it('erases replies along with their parent when force deleted', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    $nested = Commit::factory()->for($reply, 'parent')->create();

    $parent->forceDelete();

    expect(Commit::withTrashed()->whereKey([$reply->id, $nested->id])->exists())->toBeFalse();
});

it('serializes a deleted commit as a tombstone without its author or body', function (): void {
    $parent = Commit::factory()->create();
    $commit = Commit::factory()->for($parent, 'parent')->create();
    $commit->delete();

    $tombstone = Commit::withTrashed()->withCount('replies')->with('user')->findOrFail($commit->id);

    expect($tombstone->toArray())->toBe([
        'id' => $commit->id,
        'parent_id' => $parent->id,
        'deleted_at' => $tombstone->deleted_at?->toJSON(),
        'replies_count' => 0,
    ]);
});
