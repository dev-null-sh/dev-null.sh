<?php

declare(strict_types=1);

use App\Actions\CreateCommitRestoration;
use App\Actions\DeleteCommit;
use App\Actions\DeleteUser;
use App\Actions\UpdateCommitHotScores;
use App\Models\Commit;
use App\Services\TheAlgorithm;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('restores a commit within its undo window', function (): void {
    $commit = Commit::factory()->create();
    $deleted = resolve(DeleteCommit::class)->handle($commit);
    $this->travel(29)->seconds();

    resolve(CreateCommitRestoration::class)->handle($deleted);

    $this->assertNotSoftDeleted($commit);
});

it('checks the current deadline instead of a stale model timestamp', function (): void {
    $commit = Commit::factory()->create();
    $deleted = resolve(DeleteCommit::class)->handle($commit);
    $deleted->forceFill(['deleted_at' => now()->addHour()]);
    $this->travel(30)->seconds();

    resolve(CreateCommitRestoration::class)->handle($deleted);

    $this->assertSoftDeleted($commit);
});

it('does not extend the recovery window for a repeated stale delete', function (): void {
    $commit = Commit::factory()->create();
    $action = resolve(DeleteCommit::class);
    $deleted = $action->handle($commit);
    $deadline = $deleted->deleted_at;
    $this->travel(20)->seconds();

    expect($action->handle($commit)->deleted_at)->toEqual($deadline);
    expect($deleted->refresh()->deleted_at)->toEqual($deadline);
});

it('cannot restore with stale models after deleting the account', function (): void {
    $commit = Commit::factory()->create();
    $owner = $commit->user;
    $deleted = resolve(DeleteCommit::class)->handle($commit);
    resolve(DeleteUser::class)->handle($owner);

    expect(fn () => resolve(CreateCommitRestoration::class)->handle($deleted))->toThrow(ModelNotFoundException::class);
    expect(Commit::withTrashed()->count())->toBe(0);
});

it('cannot restore an authorless reply left by account deletion from a stale model', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    Commit::factory()->for($reply, 'parent')->create();
    $owner = $reply->user;
    $deleted = resolve(DeleteCommit::class)->handle($reply);

    resolve(DeleteUser::class)->handle($owner);
    resolve(CreateCommitRestoration::class)->handle($deleted);

    $this->assertSoftDeleted($reply);
    expect($reply->refresh()->user_id)->toBeNull()
        ->and($reply->toArray())->not->toHaveKeys(['body', 'user', 'user_id']);
});

it('restores the thread score when a deleted reply returns', function (): void {
    $commit = Commit::factory()->create();
    $reply = Commit::factory()->for($commit, 'parent')->create();
    resolve(UpdateCommitHotScores::class)->handle([$commit->id]);
    $deleted = resolve(DeleteCommit::class)->handle($reply);

    resolve(CreateCommitRestoration::class)->handle($deleted);

    expect($commit->fresh()->hot_score)
        ->toEqualWithDelta(resolve(TheAlgorithm::class)->score($commit->created_at, 0, 1), 1e-9);
});
