<?php

declare(strict_types=1);

use App\Actions\DeleteCommit;
use App\Actions\DeleteCommitContent;
use App\Models\Commit;
use App\Models\Poll;
use App\Models\User;
use App\Queries\RelatedCommitsQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * @return list<string>
 */
function relatedCommitIds(Commit $commit, ?User $viewer = null): array
{
    return resolve(RelatedCommitsQuery::class)->get($commit, $viewer)->pluck('id')->all();
}

it('lists the nearest top-level commits, nearest first', function (): void {
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $farther = Commit::factory()->create(['embedding' => embeddingAtDistance(0.3)]);
    $nearest = Commit::factory()->create(['embedding' => embeddingAtDistance(0.1)]);

    expect(relatedCommitIds($source))->toBe([$nearest->id, $farther->id]);
});

it('never lists the thread being read', function (): void {
    $root = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $reply = Commit::factory()->for($root, 'parent')->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->for($root, 'parent')->create(['embedding' => embeddingVector(0)]);
    $other = Commit::factory()->create(['embedding' => embeddingVector(0)]);

    expect(relatedCommitIds($reply))->toBe([$other->id])
        ->and(relatedCommitIds($root))->toBe([$other->id]);
});

it("uses a reply's own vector as the question", function (): void {
    $root = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $reply = Commit::factory()->for($root, 'parent')->create(['embedding' => embeddingVector(1)]);
    Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $closeToReply = Commit::factory()->create(['embedding' => embeddingVector(1)]);

    expect(relatedCommitIds($reply))->toBe([$closeToReply->id]);
});

it('leaves replies out', function (): void {
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $otherRoot = Commit::factory()->create(['embedding' => embeddingVector(1)]);
    Commit::factory()->for($otherRoot, 'parent')->create(['embedding' => embeddingVector(0)]);

    expect(relatedCommitIds($source))->toBeEmpty();
});

it('finds top-level commits through the vector index behind many closer replies', function (): void {
    DB::statement('set local enable_sort = off');
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $otherRoot = Commit::factory()->create(['embedding' => embeddingVector(1)]);
    foreach (range(1, 80) as $step) {
        Commit::factory()->for($otherRoot, 'parent')->create(['embedding' => embeddingAtDistance($step / 1000)]);
    }

    $related = Commit::factory()->count(3)->create(['embedding' => embeddingAtDistance(0.2)]);

    expect(relatedCommitIds($source))->toEqualCanonicalizing($related->pluck('id')->all());
});

it('drops commits past the distance cutoff', function (): void {
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->create(['embedding' => embeddingAtDistance(RelatedCommitsQuery::MAX_DISTANCE + 0.01)]);

    expect(relatedCommitIds($source))->toBeEmpty();
});

it('includes a commit right at the cutoff', function (): void {
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $atCutoff = Commit::factory()->create(['embedding' => embeddingAtDistance(RelatedCommitsQuery::MAX_DISTANCE)]);

    expect(relatedCommitIds($source))->toBe([$atCutoff->id]);
});

it('caps the rail', function (): void {
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->count(RelatedCommitsQuery::LIMIT + 1)->create(['embedding' => embeddingVector(0)]);

    expect(relatedCommitIds($source))->toHaveCount(RelatedCommitsQuery::LIMIT);
});

it('returns nothing for a commit without an embedding', function (): void {
    $source = Commit::factory()->create(['embedding' => null]);
    Commit::factory()->create(['embedding' => embeddingVector(0)]);

    expect(relatedCommitIds($source))->toBeEmpty();
});

it("returns nothing after the commit's content was erased", function (): void {
    Queue::fake();
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->create(['embedding' => embeddingVector(0)]);

    resolve(DeleteCommit::class)->handle($source);
    $this->travel(30)->seconds();
    resolve(DeleteCommitContent::class)->handle($source);

    expect(relatedCommitIds($source))->toBeEmpty();
});

it('skips candidates without an embedding', function (): void {
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $embedded = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->create(['embedding' => null]);

    expect(relatedCommitIds($source))->toBe([$embedded->id]);
});

it('hides pending and rejected commits from other viewers', function (): void {
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $approved = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->pending()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->rejected()->create(['embedding' => embeddingVector(0)]);

    expect(relatedCommitIds($source, User::factory()->create()))->toBe([$approved->id]);
});

it('shows an author their own pending commit', function (): void {
    $author = User::factory()->create();
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $pending = Commit::factory()->for($author)->pending()->create(['embedding' => embeddingVector(0)]);

    expect(relatedCommitIds($source, $author))->toBe([$pending->id]);
});

it('hides approved commits across a block in either direction', function (): void {
    $viewer = User::factory()->create();
    $blocked = User::factory()->create();
    $blocker = User::factory()->create();
    $viewer->blocking()->attach($blocked);
    $blocker->blocking()->attach($viewer);

    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $reachable = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->for($blocked)->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->for($blocker)->create(['embedding' => embeddingVector(0)]);

    expect(relatedCommitIds($source, $viewer))->toBe([$reachable->id]);
});

it('hides muted authors', function (): void {
    $viewer = User::factory()->create();
    $muted = User::factory()->create();
    $viewer->muting()->attach($muted);

    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $reachable = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->for($muted)->create(['embedding' => embeddingVector(0)]);

    expect(relatedCommitIds($source, $viewer))->toBe([$reachable->id]);
});

it('hides deleted commits', function (): void {
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $kept = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->create(['embedding' => embeddingVector(0)])->delete();

    expect(relatedCommitIds($source))->toBe([$kept->id]);
});

it('gives guests approved commits only', function (): void {
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $approved = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->pending()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->rejected()->create(['embedding' => embeddingVector(0)]);

    expect(relatedCommitIds($source))->toBe([$approved->id]);
});

it('loads the commits as the feeds do, so the card renders them', function (): void {
    $viewer = User::factory()->create();
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $commit = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $viewer->starredCommits()->attach($commit);
    Poll::factory()->for($commit)->withOptions('Tabs', 'Spaces')->create();

    $result = resolve(RelatedCommitsQuery::class)->get($source, $viewer)->sole();

    expect($result->user->is($commit->user))->toBeTrue()
        ->and($result->stars_count)->toBe(1)
        ->and($result->starred)->toBeTrue()
        ->and($result->replies_count)->toBe(0)
        ->and($result->relationLoaded('poll'))->toBeTrue()
        ->and($result->poll->options)->toHaveCount(2)
        ->and($result->getAttributes())->not->toHaveKey('embedding');
});

it('finds the candidates in one query', function (): void {
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->create(['embedding' => embeddingVector(0)]);

    DB::enableQueryLog();
    resolve(RelatedCommitsQuery::class)->get($source, null);

    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $query): bool => str_contains($query, '<=>')))->toHaveCount(1);
});

it('asks no outside service anything', function (): void {
    Http::fake();
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->create(['embedding' => embeddingVector(0)]);

    relatedCommitIds($source);

    Http::assertNothingSent();
});
