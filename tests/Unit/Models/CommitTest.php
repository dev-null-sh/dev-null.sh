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

it('deletes replies along with their parent', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    $nested = Commit::factory()->for($reply, 'parent')->create();

    $parent->delete();

    expect($reply->fresh())->toBeNull()
        ->and($nested->fresh())->toBeNull();
});
