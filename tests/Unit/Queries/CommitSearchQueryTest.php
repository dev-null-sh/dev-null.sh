<?php

declare(strict_types=1);

use App\Contracts\Services\EmbeddingProvider;
use App\Contracts\Services\RelevanceProvider;
use App\Models\Commit;
use App\Models\Poll;
use App\Models\User;
use App\Queries\CommitSearchQuery;

it('returns the commits nearest in meaning, closest first', function (): void {
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldReceive('embed')->with('a query')->once()->andReturn(embeddingVector(0));

    $close = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $far = Commit::factory()->create(['embedding' => embeddingVector(1)]);

    $results = resolve(CommitSearchQuery::class)->get('a query', null);

    expect($results->pluck('id')->all())->toBe([$close->id, $far->id]);
});

it('reads the term against the candidates with the decision model, keeping only the relevant ones', function (): void {
    $relevance = $this->mock(RelevanceProvider::class);
    $relevance->shouldReceive('decideRelevance')
        ->with('indexing strategies', ['About indexing', 'About something else'], false)
        ->once()
        ->andReturn([true, false]);

    $relevant = Commit::factory()->create(['body' => 'About indexing', 'embedding' => embeddingVector(0)]);
    Commit::factory()->create(['body' => 'About something else', 'embedding' => embeddingVector(1)]);

    expect(resolve(CommitSearchQuery::class)->get('indexing strategies', null)->pluck('id')->all())->toBe([$relevant->id]);
});

it('leaves out commits that have not been embedded', function (): void {
    $embedded = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->create(['embedding' => null]);

    expect(resolve(CommitSearchQuery::class)->get('a query', null)->pluck('id')->all())->toBe([$embedded->id]);
});

it('limits the number of results', function (): void {
    Commit::factory()->count(3)->create(['embedding' => embeddingVector(0)]);

    expect(resolve(CommitSearchQuery::class)->get('a query', null, limit: 2))->toHaveCount(2);
});

it('returns nothing, and asks for nothing, for a term shorter than three characters', function (string $term): void {
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldNotReceive('embed');

    $relevance = $this->mock(RelevanceProvider::class);
    $relevance->shouldNotReceive('decideRelevance');

    expect(resolve(CommitSearchQuery::class)->get($term, null))->toBeEmpty();
})->with(['empty' => ['   '], 'one letter' => ['a'], 'two letters' => ['on']]);

it('loads the commits as the feeds do, so the card renders them', function (): void {
    $viewer = User::factory()->create();
    $commit = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $viewer->starredCommits()->attach($commit);
    Poll::factory()->for($commit)->withOptions('Tabs', 'Spaces')->create();

    $result = resolve(CommitSearchQuery::class)->get('a query', $viewer)->sole();

    expect($result->user->is($commit->user))->toBeTrue()
        ->and($result->stars_count)->toBe(1)
        ->and($result->starred)->toBeTrue()
        ->and($result->replies_count)->toBe(0)
        ->and($result->relationLoaded('poll'))->toBeTrue()
        ->and($result->poll->options)->toHaveCount(2);
});

it('leaves out commits the viewer may not see', function (): void {
    $approved = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->pending()->create(['embedding' => embeddingVector(0)]);

    expect(resolve(CommitSearchQuery::class)->get('a query', User::factory()->create())->pluck('id')->all())->toBe([$approved->id]);
});

it('leaves out muted people and people across a block', function (): void {
    $viewer = User::factory()->create();
    $muted = User::factory()->create();
    $blocked = User::factory()->create();
    $blocker = User::factory()->create();

    $viewer->muting()->attach($muted);
    $viewer->blocking()->attach($blocked);
    $blocker->blocking()->attach($viewer);

    $reachable = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->for($muted)->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->for($blocked)->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->for($blocker)->create(['embedding' => embeddingVector(0)]);

    expect(resolve(CommitSearchQuery::class)->get('a query', $viewer)->pluck('id')->all())->toBe([$reachable->id]);
});

it('asks the decision model nothing when no commit is a candidate', function (): void {
    $relevance = $this->mock(RelevanceProvider::class);
    $relevance->shouldNotReceive('decideRelevance');

    expect(resolve(CommitSearchQuery::class)->get('a query', null))->toBeEmpty();
});

it('caches trimmed search embeddings for an hour without caching commit visibility', function (): void {
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldReceive('embed')->with('a query')->andReturn(embeddingVector(0));
    $commit = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $query = resolve(CommitSearchQuery::class);

    expect($query->get('  a query  ', null)->sole()->id)->toBe($commit->id);
    $commit->delete();
    expect($query->get('a query', null))->toBeEmpty();
    $embeddings->shouldHaveReceived('embed')->once();

    $this->travel(1)->hours();
    expect($query->get('a query', null))->toBeEmpty();
    $embeddings->shouldHaveReceived('embed')->twice();
});

it('does not cache a failed query embedding as a successful search', function (): void {
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldReceive('embed')->with('a query')->andReturnUsing(
        fn (): array => throw new UnexpectedValueException('OpenAI did not return an embedding for every input.'),
        fn (): array => embeddingVector(0),
    );
    $query = resolve(CommitSearchQuery::class);

    expect(fn () => $query->get('a query', null))->toThrow(UnexpectedValueException::class);
    expect($query->get('a query', null))->toBeEmpty();
    $embeddings->shouldHaveReceived('embed')->twice();
});
