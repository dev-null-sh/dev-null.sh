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
