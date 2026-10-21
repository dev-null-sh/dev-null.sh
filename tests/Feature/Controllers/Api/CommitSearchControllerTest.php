<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['services.openai.key' => 'sk-test']);
});

it('returns matching commits to guests, as the public API shows them', function (): void {
    fakeSearch();

    $commit = Commit::factory()->create(['body' => 'Shipped a new feature', 'embedding' => embeddingVector(0)]);
    Commit::factory()->create(['body' => 'Something else', 'embedding' => embeddingVector(1)]);

    $response = $this->getJson(route('api-commit-search.index', ['q' => 'feature']));

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $commit->id)
        ->assertJsonPath('data.0.body', 'Shipped a new feature')
        ->assertJsonPath('data.0.stars_count', 0)
        ->assertJsonPath('data.0.replies_count', 0)
        ->assertJsonPath('data.0.author.id', $commit->user->id)
        ->assertJsonMissingPath('data.0.starred')
        ->assertJsonMissingPath('data.0.status')
        ->assertJsonMissingPath('data.0.author.lane')
        ->assertJsonMissingPath('data.0.embedding')
        ->assertJsonMissingPath('commits');
});

it('shows a signed request the commits as the web would', function (): void {
    fakeSearch();

    $viewer = User::factory()->create();
    $commit = Commit::factory()->create(['body' => 'Shipped a new feature', 'embedding' => embeddingVector(0)]);
    $viewer->starredCommits()->attach($commit);

    signedApiRequest(apiKeyPairFor($viewer), 'GET', route('api-commit-search.index', ['q' => 'feature']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $commit->id)
        ->assertJsonPath('data.0.starred', true)
        ->assertJsonPath('data.0.status', 'approved')
        ->assertJsonPath('data.0.author.lane', $commit->user->lane());
});

it('leaves out muted people and people across a block for a signed request', function (): void {
    fakeSearch();

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

    signedApiRequest(apiKeyPairFor($viewer), 'GET', route('api-commit-search.index', ['q' => 'feature']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $reachable->id);
});

it('drops the candidates the decision model calls irrelevant', function (): void {
    fakeSearch([true, false]);

    $relevant = Commit::factory()->create(['body' => 'About indexing', 'embedding' => embeddingVector(0)]);
    Commit::factory()->create(['body' => 'About something else', 'embedding' => embeddingVector(1)]);

    $this->getJson(route('api-commit-search.index', ['q' => 'indexing']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $relevant->id);
});

it('returns no more than the requested limit', function (): void {
    fakeSearch();

    Commit::factory()->count(3)->create(['embedding' => embeddingVector(0)]);

    $this->getJson(route('api-commit-search.index', ['q' => 'feature', 'limit' => 2]))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('returns nothing, and asks openai nothing, for a term shorter than three characters', function (): void {
    Http::fake();
    Commit::factory()->create(['embedding' => embeddingVector(0)]);

    $this->getJson(route('api-commit-search.index', ['q' => 'ab']))
        ->assertOk()
        ->assertJsonCount(0, 'data');

    Http::assertNothingSent();
});

it('rejects overly long queries and out-of-range limits', function (array $query): void {
    $this->getJson(route('api-commit-search.index', $query))->assertUnprocessable();
})->with([
    'a query over 100 characters' => [['q' => str_repeat('a', 101)]],
    'a limit of zero' => [['q' => 'feature', 'limit' => 0]],
    'a limit over 100' => [['q' => 'feature', 'limit' => 101]],
]);

it('asks guests to slow down after 15 searches a minute', function (): void {
    Http::fake();

    for ($attempt = 0; $attempt < 15; $attempt++) {
        $this->getJson(route('api-commit-search.index', ['q' => 'ab']))->assertOk();
    }

    $this->getJson(route('api-commit-search.index', ['q' => 'ab']))
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60')
        ->assertExactJson([
            'message' => 'Slow down: too many searches. Give it a few seconds.',
            'code' => 'rate_limited',
        ]);
});
