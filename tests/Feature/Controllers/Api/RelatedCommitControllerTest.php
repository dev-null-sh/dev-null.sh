<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('responds with the related commits as the API shows commits', function (): void {
    $commit = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $related = Commit::factory()->create(['embedding' => embeddingVector(0)]);

    $this->getJson(route('api-related-commit.index', $commit))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $related->id)
        ->assertJsonPath('data.0.author.handle', $related->user->handle)
        ->assertJsonMissingPath('data.0.starred')
        ->assertJsonMissingPath('data.0.embedding');
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('shows a signed request whether it starred each related commit', function (): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);
    $commit = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $related = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $related->stars()->attach($user);

    signedApiRequest($keyPair, 'GET', route('api-related-commit.index', $commit))
        ->assertOk()
        ->assertJsonPath('data.0.id', $related->id)
        ->assertJsonPath('data.0.starred', true);
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('does not answer for a commit that is not visible', function (): void {
    $commit = Commit::factory()->pending()->create(['embedding' => embeddingVector(0)]);

    $this->getJson(route('api-related-commit.index', $commit))->assertNotFound();
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');
