<?php

declare(strict_types=1);

use App\Actions\DeleteUser;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use App\Services\PageMetadata;

it('omits public metadata for authorless account deletion tombstones', function (CommitStatus $status): void {
    $user = User::factory()->create();
    $root = Commit::factory()->create();
    $reply = Commit::factory()->for($user)->for($root, 'parent')->create(['status' => $status]);
    Commit::factory()->for($reply, 'parent')->create();
    resolve(DeleteUser::class)->handle($user);
    $tombstone = Commit::withTrashed()->findOrFail($reply->id)->loadPublicPreview()->loadCount('stars');

    $metadata = resolve(PageMetadata::class)->commit($tombstone);

    expect($metadata)->toBeNull();
})->with([
    'approved' => [CommitStatus::Approved],
    'pending' => [CommitStatus::Pending],
    'rejected' => [CommitStatus::Rejected],
]);

it('omits public metadata for commits that are not public', function (Closure $state): void {
    $commit = Commit::factory()->create();
    $state($commit);
    $commit->loadPublicPreview()->loadCount('stars');

    $metadata = resolve(PageMetadata::class)->commit($commit);

    expect($metadata)->toBeNull();
})->with([
    'pending' => [fn (Commit $commit): bool => $commit->update(['status' => CommitStatus::Pending])],
    'rejected' => [fn (Commit $commit): bool => $commit->update(['status' => CommitStatus::Rejected])],
    'deleted with author' => [fn (Commit $commit): ?bool => $commit->delete()],
    'authorless' => [fn (Commit $commit): bool => $commit->update(['user_id' => null])],
]);

it('omits an authorless parent from the metadata of a surviving reply', function (): void {
    $user = User::factory()->create();
    $root = Commit::factory()->create();
    $parent = Commit::factory()->for($user)->for($root, 'parent')->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    resolve(DeleteUser::class)->handle($user);
    $reply->loadPublicPreview()->loadCount('stars');
    $reply->setRelation('parent', Commit::withTrashed()->findOrFail($parent->id));

    $metadata = resolve(PageMetadata::class)->commit($reply);

    expect($metadata)->toMatchArray([
        'title' => $reply->user->nameWithHandle().' on /dev/null',
        'url' => route('commit.show', [$reply->user, $reply]),
        'author' => route('user.show', $reply->user),
    ]);
    expect($metadata['structuredData'])->not->toHaveKey('isPartOf');
});
