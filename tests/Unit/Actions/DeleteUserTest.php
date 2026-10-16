<?php

declare(strict_types=1);

use App\Actions\DeleteUser;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('may delete a user', function (): void {
    $user = User::factory()->create();

    $action = resolve(DeleteUser::class);

    $action->handle($user);

    expect($user->exists)->toBeFalse();
});

it('erases the commits of a deleted user along with the replies under them', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create();
    Commit::factory()->for($user)->create()->delete();
    Commit::factory()->for($commit, 'parent')->create();
    $unrelated = Commit::factory()->create();

    resolve(DeleteUser::class)->handle($user);

    expect(Commit::withTrashed()->pluck('id')->all())->toBe([$unrelated->id]);
});

it('leaves a tombstone for a reply of a deleted user that other people replied to', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();
    $reply = Commit::factory()->for($user)->for($commit, 'parent')->create();
    $otherReply = Commit::factory()->for($reply, 'parent')->create();
    $nestedReply = Commit::factory()->for($user)->for($otherReply, 'parent')->create();

    resolve(DeleteUser::class)->handle($user);

    $tombstone = Commit::withTrashed()->findOrFail($reply->id);

    expect($tombstone->trashed())->toBeTrue()
        ->and($tombstone->user_id)->toBeNull()
        ->and($tombstone->parent_id)->toBe($commit->id)
        ->and(Commit::query()->pluck('id')->sort()->values()->all())->toBe(collect([$commit->id, $otherReply->id])->sort()->values()->all())
        ->and(Commit::withTrashed()->whereKey($nestedReply->id)->exists())->toBeFalse();
});

it('erases the replies of a deleted user that only have their own replies under them', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();
    $reply = Commit::factory()->for($user)->for($commit, 'parent')->create();
    Commit::factory()->for($user)->for($reply, 'parent')->create();
    Commit::factory()->for($user)->for($commit, 'parent')->create()->delete();

    resolve(DeleteUser::class)->handle($user);

    expect(Commit::withTrashed()->pluck('id')->all())->toBe([$commit->id]);
});

it('erases the mutes and blocks of a deleted user in both directions', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $user->muting()->attach($other);
    $other->muting()->attach($user);
    $user->blocking()->attach($other);
    $other->blocking()->attach($user);

    resolve(DeleteUser::class)->handle($user);

    expect($other->muting()->count())->toBe(0)
        ->and($other->blocking()->count())->toBe(0)
        ->and($other->blockers()->count())->toBe(0);
});

it('erases the images of a deleted user and of the replies under their commits', function (): void {
    Storage::fake();

    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create();
    $reply = Commit::factory()->for($commit, 'parent')->create();
    $nestedReply = Commit::factory()->for($reply, 'parent')->create();
    $otherCommit = Commit::factory()->create();
    $tombstonedReply = Commit::factory()->for($user)->for($otherCommit, 'parent')->create();
    $otherReply = Commit::factory()->for($tombstonedReply, 'parent')->create();

    $images = collect([$commit, $reply, $nestedReply, $tombstonedReply, $otherCommit, $otherReply])
        ->map(fn (Commit $commit): CommitImage => CommitImage::factory()->for($commit)->create())
        ->each(fn (CommitImage $image): bool => Storage::disk()->put($image->path, 'image'));

    resolve(DeleteUser::class)->handle($user);

    $kept = $images->slice(4)->map(fn (CommitImage $image): string => $image->path)->sort()->values()->all();

    expect(collect(Storage::disk()->allFiles('commit-images'))->sort()->values()->all())->toBe($kept)
        ->and(CommitImage::query()->pluck('path')->sort()->values()->all())->toBe($kept);
});
