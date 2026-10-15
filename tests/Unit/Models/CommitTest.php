<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\LinkPreview;
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
            'link_preview_id',
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

it('is visible when approved or owned', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $approved = Commit::factory()->for($author)->create();
    $pending = Commit::factory()->for($author)->pending()->create();
    $rejected = Commit::factory()->for($author)->rejected()->create();

    expect($approved->isVisibleTo($viewer))->toBeTrue()
        ->and($pending->isVisibleTo($author))->toBeTrue()
        ->and($pending->isVisibleTo($viewer))->toBeFalse()
        ->and($rejected->isVisibleTo($author))->toBeTrue()
        ->and($rejected->isVisibleTo($viewer))->toBeFalse();
});

it('deletes stars with the commit', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    $user->starredCommits()->attach($commit);

    $commit->delete();

    expect($user->starredCommits()->count())->toBe(0);
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

it('may belong to a link preview', function (): void {
    $linkPreview = LinkPreview::factory()->create();
    $commit = Commit::factory()->for($linkPreview)->create();

    expect($commit->linkPreview->is($linkPreview))->toBeTrue()
        ->and(Commit::factory()->create()->linkPreview)->toBeNull();
});

it('leaves the link preview out of a tombstone', function (): void {
    $commit = Commit::factory()->for(LinkPreview::factory()->fetched())->create();
    $commit->delete();

    $tombstone = Commit::withTrashed()->with('linkPreview')->findOrFail($commit->id);

    expect($tombstone->toArray())->not->toHaveKeys(['link_preview_id', 'link_preview']);
});
