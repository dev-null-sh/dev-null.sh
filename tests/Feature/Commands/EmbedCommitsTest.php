<?php

declare(strict_types=1);

use App\Contracts\Services\EmbeddingProvider;
use App\Models\Commit;

it('embeds every commit missing an embedding, in one request', function (): void {
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldReceive('embedMany')->once()->with(['First commit', 'Second commit'])->andReturn([embeddingVector(1), embeddingVector(2)]);

    $first = Commit::factory()->create(['body' => 'First commit']);
    $second = Commit::factory()->create(['body' => 'Second commit']);

    $this->artisan('commits:embed')
        ->expectsOutputToContain('Embedded 2 commits.')
        ->assertSuccessful();

    expect(Commit::query()->whereKey($first->id)->value('embedding'))->toBe(embeddingVector(1))
        ->and(Commit::query()->whereKey($second->id)->value('embedding'))->toBe(embeddingVector(2));
});

it('leaves commits that already have an embedding alone', function (): void {
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldReceive('embedMany')->once()->with(['Needs one'])->andReturn([embeddingVector(4)]);

    Commit::factory()->create(['embedding' => embeddingVector(9)]);
    $missing = Commit::factory()->create(['body' => 'Needs one']);

    $this->artisan('commits:embed')
        ->expectsOutputToContain('Embedded 1 commits.')
        ->assertSuccessful();

    expect(Commit::query()->whereKey($missing->id)->value('embedding'))->toBe(embeddingVector(4));
});

it('leaves commits without a body alone', function (): void {
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldReceive('embedMany')->once()->with(['Needs one'])->andReturn([embeddingVector(4)]);

    $imageOnly = Commit::factory()->create(['body' => '']);
    Commit::factory()->create(['body' => 'Needs one']);

    $this->artisan('commits:embed --all')
        ->expectsOutputToContain('Embedded 1 commits.')
        ->assertSuccessful();

    expect($imageOnly->refresh()->embedding)->toBeNull();
});

it('embeds every commit again with --all', function (): void {
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldReceive('embedMany')->once()->andReturn([embeddingVector(4)]);

    $commit = Commit::factory()->create(['embedding' => embeddingVector(9)]);

    $this->artisan('commits:embed --all')
        ->expectsOutputToContain('Embedded 1 commits.')
        ->assertSuccessful();

    expect(Commit::query()->whereKey($commit->id)->value('embedding'))->toBe(embeddingVector(4));
});

it('leaves deleted commits alone', function (): void {
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldNotReceive('embedMany');

    Commit::factory()->create()->delete();

    $this->artisan('commits:embed')
        ->expectsOutputToContain('Embedded 0 commits.')
        ->assertSuccessful();
});
