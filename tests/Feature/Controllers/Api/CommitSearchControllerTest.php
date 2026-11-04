<?php

declare(strict_types=1);

use App\Contracts\Services\EmbeddingProvider;
use App\Contracts\Services\RelevanceProvider;
use App\Models\Commit;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('returns the commits nearest the term, closest first, as the commit endpoint shows them', function (): void {
    $closest = Commit::factory()->create(['body' => 'Shipped a new feature', 'embedding' => embeddingVector(0)]);
    $closest->stars()->attach($this->user);
    $other = Commit::factory()->create(['body' => 'Something else', 'embedding' => embeddingVector(1)]);

    $response = signedApiRequest($this->keyPair, 'GET', route('api-commit-search.index', ['q' => 'feature']));

    $response->assertOk()
        ->assertJsonPath('data.*.id', [$closest->id, $other->id])
        ->assertJsonPath('data.0.starred', true)
        ->assertJsonPath('data.0.author.handle', $closest->user->handle)
        ->assertJsonMissingPath('data.0.embedding');
});

it('drops the candidates the decision model calls irrelevant', function (): void {
    $relevant = Commit::factory()->create(['body' => 'About indexing', 'embedding' => embeddingVector(0)]);
    Commit::factory()->create(['body' => 'About something else', 'embedding' => embeddingVector(1)]);

    $relevance = $this->mock(RelevanceProvider::class);
    $relevance->shouldReceive('decideRelevance')->with('indexing', ['About indexing', 'About something else'], false)->once()->andReturn([true, false]);

    signedApiRequest($this->keyPair, 'GET', route('api-commit-search.index', ['q' => 'indexing']))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$relevant->id]);
});

it('returns no more than the limit asked for, and 20 by default', function (?int $limit, int $count): void {
    Commit::factory()->count(21)->create(['embedding' => embeddingVector(0)]);

    signedApiRequest($this->keyPair, 'GET', route('api-commit-search.index', array_filter(['q' => 'feature', 'limit' => $limit])))
        ->assertOk()
        ->assertJsonCount($count, 'data');
})->with([
    'the default' => [null, 20],
    'a limit' => [2, 2],
]);

it('leaves out the commits of people the user blocked', function (): void {
    $visible = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $blocked = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $this->user->blocking()->attach($blocked->user);

    signedApiRequest($this->keyPair, 'GET', route('api-commit-search.index', ['q' => 'feature']))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$visible->id]);
});

it('rejects short or long terms and out-of-range limits without embedding anything', function (array $query, string $field): void {
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldNotReceive('embed');

    signedApiRequest($this->keyPair, 'GET', route('api-commit-search.index', $query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'no term' => [[], 'q'],
    'a term under 3 characters' => [['q' => 'ab'], 'q'],
    'a term over 100 characters' => [['q' => str_repeat('a', 101)], 'q'],
    'a limit of zero' => [['q' => 'feature', 'limit' => 0], 'limit'],
    'a limit over 100' => [['q' => 'feature', 'limit' => 101], 'limit'],
]);

it('requires a signed request', function (): void {
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldNotReceive('embed');

    $this->getJson(route('api-commit-search.index', ['q' => 'feature']))->assertUnauthorized();
});
