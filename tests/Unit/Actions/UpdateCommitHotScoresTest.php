<?php

declare(strict_types=1);

use App\Actions\UpdateCommitHotScores;
use App\Dto\ThreadEngagement;
use App\Models\Commit;
use App\Models\Organization;
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
        ->toEqualWithDelta(resolve(TheAlgorithm::class)->score($commit->created_at, new ThreadEngagement(stargazers: 2, quoters: 0, repliers: 2, repliersAnsweredByAuthor: 1)), 1e-9);
});

it('counts the developers starring the commit or any reply in its thread once each, also when they reply', function (): void {
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
        ->toEqualWithDelta(resolve(TheAlgorithm::class)->score($commit->created_at, new ThreadEngagement(stargazers: 3, quoters: 0, repliers: 1, repliersAnsweredByAuthor: 0)), 1e-9);
});

it('counts the developers quoting the commit or any reply in its thread once each, apart from their replies', function (): void {
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
        ->toEqualWithDelta(resolve(TheAlgorithm::class)->score($commit->created_at, new ThreadEngagement(stargazers: 0, quoters: 3, repliers: 1, repliersAnsweredByAuthor: 0)), 1e-9);
});

it('rescores the thread of the commit a quote quotes', function (): void {
    $commit = Commit::factory()->create();
    $reply = Commit::factory()->for($commit, 'parent')->create();
    $quote = Commit::factory()->for($reply, 'quotedCommit')->create();

    resolve(UpdateCommitHotScores::class)->handle([$quote->id]);

    expect($commit->fresh()->hot_score)
        ->toEqualWithDelta(resolve(TheAlgorithm::class)->score($commit->created_at, new ThreadEngagement(stargazers: 0, quoters: 1, repliers: 1, repliersAnsweredByAuthor: 0)), 1e-9);
});

it('does not count quotes of replies others cannot see', function (): void {
    $commit = Commit::factory()->create();
    $reply = Commit::factory()->rejected()->for($commit, 'parent')->create();
    Commit::factory()->for($reply, 'quotedCommit')->create();

    resolve(UpdateCommitHotScores::class)->handle([$commit->id]);

    expect($commit->fresh()->hot_score)
        ->toEqualWithDelta(resolve(TheAlgorithm::class)->score($commit->created_at, ThreadEngagement::none()), 1e-9);
});

it('scores many threads at once, each by its own engagement', function (): void {
    $quiet = Commit::factory()->create();
    $starred = Commit::factory()->create();
    $starred->stars()->attach(User::factory()->count(3)->create());
    $repliedTo = Commit::factory()->create();
    $reply = Commit::factory()->for($repliedTo, 'parent')->create();

    resolve(UpdateCommitHotScores::class)->handle([$quiet->id, $starred->id, $reply->id]);

    expect($quiet->fresh()->hot_score)->toEqualWithDelta(resolve(TheAlgorithm::class)->score($quiet->created_at, ThreadEngagement::none()), 1e-9)
        ->and($starred->fresh()->hot_score)->toEqualWithDelta(resolve(TheAlgorithm::class)->score($starred->created_at, new ThreadEngagement(stargazers: 3, quoters: 0, repliers: 0, repliersAnsweredByAuthor: 0)), 1e-9)
        ->and($repliedTo->fresh()->hot_score)->toEqualWithDelta(resolve(TheAlgorithm::class)->score($repliedTo->created_at, new ThreadEngagement(stargazers: 0, quoters: 0, repliers: 1, repliersAnsweredByAuthor: 0)), 1e-9);
});

it('counts the developers replying whom the author answers', function (): void {
    $commit = Commit::factory()->create();
    $answered = Commit::factory()->for($commit, 'parent')->create();
    $unanswered = Commit::factory()->for($commit, 'parent')->create();
    Commit::factory()->for($commit->user)->for($answered, 'parent')->create();
    Commit::factory()->for($commit->user)->for($answered, 'parent')->create();
    Commit::factory()->for($answered->user)->for($unanswered, 'parent')->create();
    Commit::factory()->pending()->for($commit->user)->for($unanswered, 'parent')->create();
    Commit::factory()->for($commit->user)->for($unanswered, 'parent')->create()->delete();

    resolve(UpdateCommitHotScores::class)->handle([$commit->id]);

    expect($commit->fresh()->hot_score)
        ->toEqualWithDelta(resolve(TheAlgorithm::class)->score($commit->created_at, new ThreadEngagement(stargazers: 0, quoters: 0, repliers: 2, repliersAnsweredByAuthor: 1)), 1e-9);
});

it('excludes each threads own author without excluding their engagement in another thread', function (): void {
    $author = User::factory()->create();
    $own = Commit::factory()->for($author)->create();
    $other = Commit::factory()->create();

    foreach ([$own, $other] as $commit) {
        $commit->stars()->attach($author);
        Commit::factory()->for($author)->for($commit, 'parent')->create();
        Commit::factory()->for($author)->for($commit, 'quotedCommit')->create();
    }

    resolve(UpdateCommitHotScores::class)->handle([$own->id, $other->id]);

    expect($own->fresh()->hot_score)->toEqualWithDelta(resolve(TheAlgorithm::class)->score($own->created_at, ThreadEngagement::none()), 1e-9)
        ->and($other->fresh()->hot_score)->toEqualWithDelta(resolve(TheAlgorithm::class)->score($other->created_at, new ThreadEngagement(stargazers: 1, quoters: 1, repliers: 1, repliersAnsweredByAuthor: 0)), 1e-9);
});

it('counts engagement in an authorless thread but not replies or quotes without an author', function (): void {
    $commit = Commit::factory()->create(['author_id' => null]);
    $commit->stars()->attach($commit->user);
    Commit::factory()->for($commit->user)->for($commit, 'parent')->create();
    Commit::factory()->for($commit->user)->for($commit, 'quotedCommit')->create();
    Commit::factory()->for($commit, 'parent')->create(['author_id' => null]);
    Commit::factory()->for($commit, 'quotedCommit')->create(['author_id' => null]);

    resolve(UpdateCommitHotScores::class)->handle([$commit->id]);

    expect($commit->fresh()->hot_score)
        ->toEqualWithDelta(resolve(TheAlgorithm::class)->score($commit->created_at, new ThreadEngagement(stargazers: 1, quoters: 1, repliers: 1, repliersAnsweredByAuthor: 0)), 1e-9);
});

it('excludes the organization author while counting the member who posted its thread', function (): void {
    $organization = Organization::factory()->create();
    $commit = Commit::factory()->create(['author_id' => $organization->id]);
    $commit->stars()->attach($commit->user);
    $reply = Commit::factory()->for($commit->user)->for($commit, 'parent')->create();
    Commit::factory()->for($reply, 'parent')->create(['author_id' => $organization->id]);
    Commit::factory()->for($commit->user)->for($commit, 'quotedCommit')->create();
    Commit::factory()->for($commit, 'quotedCommit')->create(['author_id' => $organization->id]);

    resolve(UpdateCommitHotScores::class)->handle([$commit->id]);

    expect($commit->fresh()->hot_score)
        ->toEqualWithDelta(resolve(TheAlgorithm::class)->score($commit->created_at, new ThreadEngagement(stargazers: 1, quoters: 1, repliers: 1, repliersAnsweredByAuthor: 1)), 1e-9);
});

it('leaves deleted threads as they are', function (): void {
    $commit = Commit::factory()->create(['hot_score' => 12.5]);
    $commit->delete();

    resolve(UpdateCommitHotScores::class)->handle([$commit->id]);

    expect(Commit::withTrashed()->findOrFail($commit->id)->hot_score)->toBe(12.5);
});
