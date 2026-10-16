<?php

declare(strict_types=1);

use App\Actions\UpdateCommitHotScores;
use App\Models\Commit;
use App\Models\User;
use App\Services\TheAlgorithm;

it('scores a commit by its stars and the other developers replying anywhere in its thread', function (): void {
    $commit = Commit::factory()->create();
    $commit->stars()->attach(User::factory()->count(2)->create());

    $replier = User::factory()->create();
    $reply = Commit::factory()->for($replier)->for($commit, 'parent')->create();
    $nested = Commit::factory()->for($replier)->for($reply, 'parent')->create();
    Commit::factory()->for($nested, 'parent')->create();
    Commit::factory()->for($commit->user)->for($reply, 'parent')->create();
    Commit::factory()->pending()->for($commit, 'parent')->create();
    Commit::factory()->for($commit, 'parent')->create()->delete();

    resolve(UpdateCommitHotScores::class)->handle([$commit->id]);

    expect($commit->fresh()->hot_score)
        ->toEqualWithDelta(resolve(TheAlgorithm::class)->score($commit->created_at, 2, 2), 1e-9);
});

it('counts the developers starring the commit or any reply in its thread once each, and not again when they reply', function (): void {
    $commit = Commit::factory()->create();
    $replier = User::factory()->create();
    $reply = Commit::factory()->for($replier)->for($commit, 'parent')->create();
    $nested = Commit::factory()->for($replier)->for($reply, 'parent')->create();

    $stargazer = User::factory()->create();
    $commit->stars()->attach([$stargazer->id, $replier->id]);
    $reply->stars()->attach([$stargazer->id, $commit->user_id]);
    $nested->stars()->attach([$stargazer->id, User::factory()->create()->id]);

    Commit::factory()->pending()->for($commit, 'parent')->create()->stars()->attach(User::factory()->create());
    $deleted = Commit::factory()->for($commit, 'parent')->create();
    $deleted->stars()->attach(User::factory()->create());
    $deleted->delete();

    resolve(UpdateCommitHotScores::class)->handle([$commit->id]);

    expect($commit->fresh()->hot_score)
        ->toEqualWithDelta(resolve(TheAlgorithm::class)->score($commit->created_at, 2, 1), 1e-9);
});

it('counts the developers quoting the commit or any reply in its thread, as quotes show among the replies', function (): void {
    $commit = Commit::factory()->create();
    $replier = User::factory()->create();
    $reply = Commit::factory()->for($replier)->for($commit, 'parent')->create();

    Commit::factory()->for($replier)->for($commit, 'quotedCommit')->create();
    Commit::factory()->for($commit, 'quotedCommit')->create();
    Commit::factory()->for($reply, 'quotedCommit')->create();
    Commit::factory()->for($commit->user)->for($commit, 'quotedCommit')->create();
    Commit::factory()->pending()->for($commit, 'quotedCommit')->create();
    Commit::factory()->for($commit, 'quotedCommit')->create()->delete();

    resolve(UpdateCommitHotScores::class)->handle([$commit->id]);

    expect($commit->fresh()->hot_score)
        ->toEqualWithDelta(resolve(TheAlgorithm::class)->score($commit->created_at, 0, 3), 1e-9);
});

it('rescores the thread of the commit a quote quotes', function (): void {
    $commit = Commit::factory()->create();
    $reply = Commit::factory()->for($commit, 'parent')->create();
    $quote = Commit::factory()->for($reply, 'quotedCommit')->create();

    resolve(UpdateCommitHotScores::class)->handle([$quote->id]);

    expect($commit->fresh()->hot_score)
        ->toEqualWithDelta(resolve(TheAlgorithm::class)->score($commit->created_at, 0, 2), 1e-9);
});

it('does not count quotes of replies others cannot see', function (): void {
    $commit = Commit::factory()->create();
    $reply = Commit::factory()->rejected()->for($commit, 'parent')->create();
    Commit::factory()->for($reply, 'quotedCommit')->create();

    resolve(UpdateCommitHotScores::class)->handle([$commit->id]);

    expect($commit->fresh()->hot_score)
        ->toEqualWithDelta(resolve(TheAlgorithm::class)->score($commit->created_at, 0, 0), 1e-9);
});
