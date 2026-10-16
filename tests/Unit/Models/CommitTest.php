<?php

declare(strict_types=1);

use App\Actions\DeleteUser;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\CommitImage;
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
            'series_id',
            'series_position',
            'series_size',
            'body_segments',
        ]);
});

it('splits its body into segments', function (): void {
    $commit = Commit::factory()->create(['body' => 'run `make`']);

    expect($commit->toArray()['body_segments'])->toBe([
        ['type' => 'text', 'text' => 'run '],
        ['type' => 'code', 'code' => 'make'],
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

it('checks account deletion tombstone visibility without an author', function (CommitStatus $status, bool $authenticated): void {
    $author = User::factory()->create();
    $root = Commit::factory()->create();
    $parent = Commit::factory()->for($author)->for($root, 'parent')->create(['status' => $status]);
    Commit::factory()->for($parent, 'parent')->create();
    resolve(DeleteUser::class)->handle($author);
    $tombstone = Commit::withTrashed()->findOrFail($parent->id);

    expect($tombstone->isVisibleTo($authenticated ? User::factory()->create() : null))
        ->toBe($status === CommitStatus::Approved);
})->with([
    'approved' => [CommitStatus::Approved],
    'pending' => [CommitStatus::Pending],
    'rejected' => [CommitStatus::Rejected],
])->with([true, false]);

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

it('is not visible across a block in either direction', function (bool $viewerBlocks): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $commit = Commit::factory()->for($author)->create();

    $viewerBlocks ? $viewer->blocking()->attach($author) : $author->blocking()->attach($viewer);

    expect($commit->isVisibleTo($viewer))->toBeFalse()
        ->and($commit->isVisibleTo($author))->toBeTrue();
})->with([true, false]);

it('has images in order', function (): void {
    $commit = Commit::factory()->create();
    $second = CommitImage::factory()->for($commit)->create(['position' => 1]);
    $first = CommitImage::factory()->for($commit)->create(['position' => 0]);

    expect($commit->images->modelKeys())->toBe([$first->id, $second->id]);
});

it('shows images when approved, to the author, or to moderators', function (): void {
    config(['app.admins' => ['admin']]);

    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $moderator = User::factory()->create(['username' => 'admin']);
    $approved = Commit::factory()->for($author)->create();
    $pending = Commit::factory()->for($author)->pending()->create();
    $rejected = Commit::factory()->for($author)->rejected()->create();

    expect($approved->imagesAreVisibleTo(null))->toBeTrue()
        ->and($approved->imagesAreVisibleTo($viewer))->toBeTrue()
        ->and($pending->imagesAreVisibleTo(null))->toBeFalse()
        ->and($pending->imagesAreVisibleTo($viewer))->toBeFalse()
        ->and($pending->imagesAreVisibleTo($author))->toBeTrue()
        ->and($pending->imagesAreVisibleTo($moderator))->toBeTrue()
        ->and($rejected->imagesAreVisibleTo($viewer))->toBeFalse()
        ->and($rejected->imagesAreVisibleTo($moderator))->toBeTrue();

    $approved->delete();

    expect($approved->imagesAreVisibleTo($author))->toBeFalse();
});

it('never shows the images of a deleted commit in its tombstone', function (): void {
    $commit = Commit::factory()->create();
    CommitImage::factory()->for($commit)->create();
    $commit->delete();

    $tombstone = Commit::withTrashed()->with('images')->findOrFail($commit->id);

    expect($tombstone->toArray())->not->toHaveKey('images');
});
