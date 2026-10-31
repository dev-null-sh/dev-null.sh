<?php

declare(strict_types=1);

use App\Contracts\Services\EmbeddingProvider;
use App\Contracts\Services\RelevanceProvider;
use App\Models\Commit;
use App\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('is for members only, as each search pays for AI', function (): void {
    $this->app['auth']->forgetGuards();

    $this->getJson('/search/commits?q=feature')->assertUnauthorized();
});

it('returns the commits matching the query, with what the card renders', function (): void {
    $commit = Commit::factory()->create(['body' => 'Shipped a new feature', 'embedding' => embeddingVector(0)]);
    Commit::factory()->create(['body' => 'Something else', 'embedding' => embeddingVector(1)]);

    $response = $this->getJson('/search/commits?q=feature');

    $response->assertOk()
        ->assertJsonCount(2, 'commits')
        ->assertJsonPath('commits.0.id', $commit->id)
        ->assertJsonPath('commits.0.stars_count', 0)
        ->assertJsonPath('commits.0.starred', false)
        ->assertJsonPath('commits.0.replies_count', 0)
        ->assertJsonPath('commits.0.user.id', $commit->user->id)
        ->assertJsonStructure(['commits' => [['body_segments', 'created_at']]])
        ->assertJsonMissingPath('commits.0.embedding');
});

it('drops the candidates the decision model calls irrelevant', function (): void {
    $relevance = $this->mock(RelevanceProvider::class);
    $relevance->shouldReceive('decideRelevance')->with('indexing', ['About indexing', 'About something else'], false)->once()->andReturn([true, false]);

    $relevant = Commit::factory()->create(['body' => 'About indexing', 'embedding' => embeddingVector(0)]);
    Commit::factory()->create(['body' => 'About something else', 'embedding' => embeddingVector(1)]);

    $this->getJson('/search/commits?q=indexing')
        ->assertOk()
        ->assertJsonCount(1, 'commits')
        ->assertJsonPath('commits.0.id', $relevant->id);
});

it('returns no more than the requested limit', function (): void {
    Commit::factory()->count(3)->create(['embedding' => embeddingVector(0)]);

    $this->getJson('/search/commits?q=feature&limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'commits');
});

it('returns nothing, and embeds nothing, for a term shorter than three characters', function (): void {
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldNotReceive('embed');
    Commit::factory()->create(['embedding' => embeddingVector(0)]);

    $this->getJson('/search/commits?q=ab')
        ->assertOk()
        ->assertJsonCount(0, 'commits');
});

it('rejects overly long queries and out-of-range limits', function (string $query): void {
    $this->getJson('/search/commits?'.$query)->assertUnprocessable();
})->with([
    'a query over 100 characters' => ['q='.str_repeat('a', 101)],
    'a limit of zero' => ['q=feature&limit=0'],
    'a limit over 100' => ['q=feature&limit=101'],
]);

it('asks people to slow down after 15 searches a minute', function (): void {
    for ($attempt = 0; $attempt < 15; $attempt++) {
        $this->getJson('/search/commits?q=ab')->assertOk();
    }

    $this->getJson('/search/commits?q=ab')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60')
        ->assertExactJson(['message' => 'Slow down: too many searches. Give it a few seconds.']);
});
