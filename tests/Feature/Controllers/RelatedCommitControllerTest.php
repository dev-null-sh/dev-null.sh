<?php

declare(strict_types=1);

use App\Actions\DeleteCommit;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('responds with the related commits, without their vectors', function (): void {
    $viewer = User::factory()->create();
    $commit = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $related = Commit::factory()->create(['embedding' => embeddingVector(0)]);

    $this->actingAs($viewer)
        ->getJson(route('related-commit.index', $commit))
        ->assertOk()
        ->assertJsonCount(1, 'commits')
        ->assertJsonPath('commits.0.id', $related->id)
        ->assertJsonPath('commits.0.user.handle', $related->user->handle)
        ->assertJsonMissingPath('commits.0.embedding');
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('lets guests see the related commits of an approved commit', function (): void {
    $commit = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $related = Commit::factory()->create(['embedding' => embeddingVector(0)]);

    $this->getJson(route('related-commit.index', $commit))
        ->assertOk()
        ->assertJsonPath('commits.0.id', $related->id);
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('does not answer for a commit that is not visible', function (): void {
    $commit = Commit::factory()->pending()->create(['embedding' => embeddingVector(0)]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('related-commit.index', $commit))
        ->assertNotFound();
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('does not answer across a block', function (): void {
    $viewer = User::factory()->create();
    $commit = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $viewer->blocking()->attach($commit->user);

    $this->actingAs($viewer)
        ->getJson(route('related-commit.index', $commit))
        ->assertNotFound();
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('does not answer for a deleted commit', function (): void {
    Queue::fake();
    $commit = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    resolve(DeleteCommit::class)->handle($commit);

    $this->getJson(route('related-commit.index', $commit))->assertNotFound();
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');

it('limits each guest IP without limiting other visitors', function (): void {
    $commit = Commit::factory()->create(['embedding' => embeddingVector(0)]);

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1']);

    foreach (range(1, 60) as $attempt) {
        $this->getJson(route('related-commit.index', $commit))->assertOk();
    }

    $this->getJson(route('related-commit.index', $commit))->assertTooManyRequests();

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2']);

    $this->getJson(route('related-commit.index', $commit))->assertOk();
})->skip(fn (): bool => pgvectorLacksIterativeScan(), 'Needs pgvector 0.8 or newer for hnsw.iterative_scan.');
