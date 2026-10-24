<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['ai.providers.openai.key' => 'sk-test']);
});

it('finds commits for guests, closest first, as the API shows commits', function (): void {
    fakeSearch();

    // The farther one first, so the order comes from the distance, not from when they were made.
    Commit::factory()->create(['body' => 'Something else', 'embedding' => embeddingVector(1)]);
    $commit = Commit::factory()->create(['body' => 'Shipped a new feature', 'embedding' => embeddingVector(0)]);

    $this->getJson('/api/v1/search/commits?q=feature')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $commit->id)
        ->assertJsonPath('data.0.html_url', route('commit.show', [$commit->user, $commit]))
        ->assertJsonPath('data.0.author.username', $commit->user->username)
        ->assertJsonPath('data.0.stars_count', 0)
        ->assertJsonPath('data.0.replies_count', 0)
        ->assertJsonMissingPath('data.0.embedding')
        ->assertJsonMissingPath('data.0.body_segments');
});

it('shows a guest which commit a reply answers only when that commit is approved', function (): void {
    fakeSearch();

    $approved = Commit::factory()->create();
    $pending = Commit::factory()->pending()->create();
    $toApproved = Commit::factory()->create(['parent_id' => $approved->id, 'embedding' => embeddingVector(0)]);
    $toPending = Commit::factory()->create(['parent_id' => $pending->id, 'embedding' => embeddingVector(1)]);

    $this->getJson('/api/v1/search/commits?q=reply')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$toApproved->id, $toPending->id])
        ->assertJsonPath('data.*.parent_id', [$approved->id, null]);
});

it('gives a signed request its commits as the web shows them to that user', function (): void {
    fakeSearch();

    $viewer = User::factory()->create();
    $keyPair = apiKeyPairFor($viewer);
    $commit = Commit::factory()->create(['body' => 'Shipped a new feature', 'embedding' => embeddingVector(0)]);
    $viewer->starredCommits()->attach($commit);

    signedApiRequest($keyPair, 'GET', route('api-commit-search.index', ['q' => 'feature']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $commit->id)
        ->assertJsonPath('data.0.starred', true)
        ->assertJsonPath('data.0.stars_count', 1)
        ->assertJsonStructure(['data' => [['body_segments', 'status']]]);
});

it('finds a pending commit for its signed author, and never for a guest', function (): void {
    fakeSearch();

    $author = User::factory()->create();
    $keyPair = apiKeyPairFor($author);
    $pending = Commit::factory()->pending()->for($author)->create(['embedding' => embeddingVector(0)]);
    $approved = Commit::factory()->create(['embedding' => embeddingVector(1)]);

    $this->getJson('/api/v1/search/commits?q=feature')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$approved->id]);

    signedApiRequest($keyPair, 'GET', route('api-commit-search.index', ['q' => 'feature']))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$pending->id, $approved->id]);
});

it('drops the candidates the decision model calls irrelevant', function (): void {
    fakeSearch([true, false]);

    $relevant = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->create(['embedding' => embeddingVector(1)]);

    $this->getJson('/api/v1/search/commits?q=indexing')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$relevant->id]);
});

it('returns no more than the requested limit', function (): void {
    fakeSearch();

    Commit::factory()->count(3)->create(['embedding' => embeddingVector(0)]);

    $this->getJson('/api/v1/search/commits?q=feature&limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('returns nothing, and asks openai nothing, for a term shorter than three characters', function (): void {
    Http::fake();
    Commit::factory()->create(['embedding' => embeddingVector(0)]);

    $this->getJson('/api/v1/search/commits?q=ab')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    Http::assertNothingSent();
});

it('rejects overly long queries and out-of-range limits', function (string $query): void {
    $this->getJson('/api/v1/search/commits?'.$query)->assertUnprocessable();
})->with([
    'a query over 100 characters' => ['q='.str_repeat('a', 101)],
    'a limit of zero' => ['q=feature&limit=0'],
    'a limit over 100' => ['q=feature&limit=101'],
]);

it('asks guests to slow down after 15 searches a minute, as the web does', function (): void {
    Http::fake();

    for ($attempt = 0; $attempt < 15; $attempt++) {
        $this->getJson('/api/v1/search/commits?q=ab')->assertOk();
    }

    $this->getJson('/api/v1/search/commits?q=ab')
        ->assertTooManyRequests()
        ->assertExactJson(['message' => 'Slow down: too many searches. Give it a few seconds.']);
});
