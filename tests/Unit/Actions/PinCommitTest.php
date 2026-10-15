<?php

declare(strict_types=1);

use App\Actions\PinCommit;
use App\Actions\UnpinCommit;
use App\Models\Commit;
use App\Models\User;

it('reasserts a pin using a stale user snapshot and ignores a stale unpin', function (): void {
    $user = User::factory()->create();
    $first = Commit::factory()->for($user)->create();
    $replacement = Commit::factory()->for($user)->create();
    $user->update(['pinned_commit_id' => $first->id]);

    resolve(PinCommit::class)->handle($user->fresh(), $replacement);
    resolve(UnpinCommit::class)->handle($user, $first);

    expect($user->fresh()->pinned_commit_id)->toBe($replacement->id);

    resolve(PinCommit::class)->handle($user, $first);
    expect($user->fresh()->pinned_commit_id)->toBe($first->id);
});

it('can delete an account with a pin and its commits', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create();
    $user->update(['pinned_commit_id' => $commit->id]);

    $this->actingAs($user)->delete(route('user.destroy'))->assertRedirectToRoute('home');

    expect($user->fresh())->toBeNull()
        ->and(Commit::withTrashed()->find($commit->id))->toBeNull();
});
