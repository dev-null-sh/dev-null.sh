<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\Talk;
use App\Models\User;
use Illuminate\Support\Str;

it('hides unpublished talks from everyone', function (): void {
    $talk = Talk::factory()->create();

    expect($talk->isPublished())->toBeFalse()
        ->and($talk->isVisibleTo(null))->toBeFalse()
        ->and($talk->isVisibleTo(User::factory()->create()))->toBeFalse();
});

it('defers published talk visibility to the anchor commit', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $commit = Commit::factory()->for($author)->create(['status' => CommitStatus::Pending]);
    $talk = Talk::factory()->for($author)->create();
    $talk->forceFill([
        'commit_id' => $commit->id,
        'published_at' => now(),
    ])->save();

    expect($talk->isPublished())->toBeTrue()
        ->and($talk->isVisibleTo($author))->toBeTrue()
        ->and($talk->isVisibleTo($viewer))->toBeFalse()
        ->and($talk->isVisibleTo(null))->toBeFalse();
});

it('hides a published talk whose anchor commit is gone', function (): void {
    $talk = Talk::factory()->make(['user_id' => User::factory()->create()->id]);
    $talk->forceFill([
        'commit_id' => (string) Str::uuid(),
        'published_at' => now(),
    ]);
    $talk->setRelation('commit', null);

    expect($talk->isPublished())->toBeTrue()
        ->and($talk->isVisibleTo(null))->toBeFalse();
});
