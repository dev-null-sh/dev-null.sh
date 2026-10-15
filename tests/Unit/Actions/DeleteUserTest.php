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

it('erases the images of a deleted user and of the replies under their commits', function (): void {
    Storage::fake();

    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create();
    $reply = Commit::factory()->for($commit, 'parent')->create();
    $nestedReply = Commit::factory()->for($reply, 'parent')->create();

    $images = collect([$commit, $reply, $nestedReply, Commit::factory()->create()])
        ->map(fn (Commit $commit): CommitImage => CommitImage::factory()->for($commit)->create())
        ->each(fn (CommitImage $image): bool => Storage::disk()->put($image->path, 'image'));

    resolve(DeleteUser::class)->handle($user);

    expect(Storage::disk()->allFiles('commit-images'))->toBe([$images->last()->path]);
});
