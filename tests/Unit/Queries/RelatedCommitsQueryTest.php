<?php

declare(strict_types=1);

use App\Actions\DeleteCommit;
use App\Actions\DeleteCommitContent;
use App\Models\Commit;
use App\Models\Poll;
use App\Models\User;
use App\Queries\RelatedCommitsQuery;
use Illuminate\Http\Client\Request;
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
    fakeSearch();
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $farther = Commit::factory()->create(['embedding' => embeddingAtDistance(0.3)]);
    $nearest = Commit::factory()->create(['embedding' => embeddingAtDistance(0.1)]);

    expect(relatedCommitIds($source))->toBe([$nearest->id, $farther->id]);
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('never lists the thread being read', function (): void {
    fakeSearch();
    $root = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $reply = Commit::factory()->for($root, 'parent')->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->for($root, 'parent')->create(['embedding' => embeddingVector(0)]);
    $other = Commit::factory()->create(['embedding' => embeddingVector(0)]);

    expect(relatedCommitIds($reply))->toBe([$other->id])
        ->and(relatedCommitIds($root))->toBe([$other->id]);
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it("uses a reply's own vector as the question", function (): void {
    fakeSearch([true, false]);
    $root = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $reply = Commit::factory()->for($root, 'parent')->create(['embedding' => embeddingVector(1)]);
    Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $closeToReply = Commit::factory()->create(['embedding' => embeddingVector(1)]);

    expect(relatedCommitIds($reply))->toBe([$closeToReply->id]);
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('leaves replies out', function (): void {
    fakeSearch([false]);
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $otherRoot = Commit::factory()->create(['embedding' => embeddingVector(1)]);
    Commit::factory()->for($otherRoot, 'parent')->create(['embedding' => embeddingVector(0)]);

    expect(relatedCommitIds($source))->toBeEmpty();
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('finds top-level commits through the vector index behind many closer replies', function (): void {
    fakeSearch();
    DB::statement('set local enable_sort = off');
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $otherRoot = Commit::factory()->create(['embedding' => embeddingVector(1)]);
    foreach (range(1, 80) as $step) {
        Commit::factory()->for($otherRoot, 'parent')->create(['embedding' => embeddingAtDistance($step / 1000)]);
    }

    $related = Commit::factory()->count(3)->create(['embedding' => embeddingAtDistance(0.2)]);

    expect(relatedCommitIds($source))->toEqualCanonicalizing($related->pluck('id')->all());
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('drops candidates the search relevance model rejects', function (): void {
    fakeSearch([false]);
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->create(['embedding' => embeddingAtDistance(0.1)]);

    expect(relatedCommitIds($source))->toBeEmpty();
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('keeps relevant candidates beyond the former distance cutoff', function (): void {
    fakeSearch([true]);
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $related = Commit::factory()->create(['embedding' => embeddingAtDistance(0.8)]);

    expect(relatedCommitIds($source))->toBe([$related->id]);
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('caps the rail', function (): void {
    fakeSearch();
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->count(RelatedCommitsQuery::LIMIT + 1)->create(['embedding' => embeddingVector(0)]);

    expect(relatedCommitIds($source))->toHaveCount(RelatedCommitsQuery::LIMIT);
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('returns nothing for a commit without an embedding', function (): void {
    $source = Commit::factory()->create(['embedding' => null]);
    Commit::factory()->create(['embedding' => embeddingVector(0)]);

    expect(relatedCommitIds($source))->toBeEmpty();
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it("returns nothing after the commit's content was erased", function (): void {
    Queue::fake();
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->create(['embedding' => embeddingVector(0)]);

    resolve(DeleteCommit::class)->handle($source);
    $this->travel(30)->seconds();
    resolve(DeleteCommitContent::class)->handle($source);

    expect(relatedCommitIds($source))->toBeEmpty();
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('skips candidates without an embedding', function (): void {
    fakeSearch();
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $embedded = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->create(['embedding' => null]);

    expect(relatedCommitIds($source))->toBe([$embedded->id]);
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('hides pending and rejected commits from other viewers', function (): void {
    fakeSearch();
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $approved = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->pending()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->rejected()->create(['embedding' => embeddingVector(0)]);

    expect(relatedCommitIds($source, User::factory()->create()))->toBe([$approved->id]);
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('shows an author their own pending commit', function (): void {
    fakeSearch();
    $author = User::factory()->create();
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $pending = Commit::factory()->for($author)->pending()->create(['embedding' => embeddingVector(0)]);

    expect(relatedCommitIds($source, $author))->toBe([$pending->id]);
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('hides approved commits across a block in either direction', function (): void {
    fakeSearch();
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
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('hides muted authors', function (): void {
    fakeSearch();
    $viewer = User::factory()->create();
    $muted = User::factory()->create();
    $viewer->muting()->attach($muted);

    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $reachable = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->for($muted)->create(['embedding' => embeddingVector(0)]);

    expect(relatedCommitIds($source, $viewer))->toBe([$reachable->id]);
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('hides deleted commits', function (): void {
    fakeSearch();
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $kept = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->create(['embedding' => embeddingVector(0)])->delete();

    expect(relatedCommitIds($source))->toBe([$kept->id]);
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('gives guests approved commits only', function (): void {
    fakeSearch();
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $approved = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->pending()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->rejected()->create(['embedding' => embeddingVector(0)]);

    expect(relatedCommitIds($source))->toBe([$approved->id]);
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('loads the commits as the feeds do, so the card renders them', function (): void {
    fakeSearch();
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
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('finds the candidates in one query', function (): void {
    fakeSearch();
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->create(['embedding' => embeddingVector(0)]);

    DB::enableQueryLog();
    resolve(RelatedCommitsQuery::class)->get($source, null);

    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $query): bool => str_contains($query, '<=>')))->toHaveCount(1);
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('judges relevance against the source body without embedding it again', function (): void {
    fakeSearch();
    $source = Commit::factory()->create(['body' => 'Database indexing', 'embedding' => embeddingVector(0)]);
    Commit::factory()->create(['embedding' => embeddingVector(0)]);

    relatedCommitIds($source);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/decisions')
        && str_contains($request['input'], 'Query: Database indexing'));
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/embeddings'));
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');
