<?php

declare(strict_types=1);

use App\Contracts\Services\HeadlineProvider;
use App\Models\Commit;
use App\Models\Headline;
use Illuminate\Support\Facades\Log;

it('backfills sequential batches so later commits reuse earlier headlines', function (): void {
    $commits = Commit::factory()->count(21)->create(['body' => 'Laravel AI features']);
    $provider = $this->mock(HeadlineProvider::class);
    $provider->shouldReceive('candidates')->andReturn(range(0, 19), [0]);
    $provider->shouldReceive('matches')->once()->with(Mockery::type('array'), [])->andReturn([]);
    $provider->shouldReceive('generate')->once()->andReturn(array_fill(0, 20, 'Laravel AI SDK'));
    $provider->shouldReceive('deduplicate')->once()->andReturn(array_fill(0, 20, 'Laravel AI SDK'));
    $provider->shouldReceive('matches')->once()->with(['Laravel AI features'], ['Laravel AI SDK'])->andReturn([0 => ['Laravel AI SDK']]);
    $provider->shouldReceive('generate')->once()->with([])->andReturn([]);
    $provider->shouldReceive('deduplicate')->once()->with([])->andReturn([]);
    Log::shouldReceive('info')->once()->with('Headline batch completed', [
        'commits' => 20, 'suitable_candidates' => 20, 'linked_existing' => 0, 'new_headlines' => 1,
    ]);
    Log::shouldReceive('info')->once()->with('Headline batch completed', [
        'commits' => 1, 'suitable_candidates' => 1, 'linked_existing' => 1, 'new_headlines' => 0,
    ]);

    $this->artisan('commits:link-headlines')->expectsOutputToContain('Processed 21 commits for headlines.')->assertSuccessful();
    $this->artisan('commits:link-headlines')->expectsOutputToContain('Processed 0 commits for headlines.')->assertSuccessful();

    expect(Headline::query()->sole()->commits()->count())->toBe(21)
        ->and($commits->last()->headlines()->sole()->name)->toBe('Laravel AI SDK');

    Headline::query()->delete();

    $this->artisan('commits:link-headlines')->expectsOutputToContain('Processed 0 commits for headlines.')->assertSuccessful();
});

it('backfills only approved nonempty unassigned roots', function (): void {
    $root = Commit::factory()->create();
    Commit::factory()->for($root, 'parent')->create();
    Commit::factory()->pending()->create();
    Commit::factory()->rejected()->create();
    Commit::factory()->create(['body' => '']);
    Commit::factory()->create()->delete();
    $root->headlines()->attach(Headline::factory()->create());
    $this->mock(HeadlineProvider::class)->shouldNotReceive('candidates');

    $this->artisan('commits:link-headlines')->expectsOutputToContain('Processed 0 commits for headlines.')->assertSuccessful();
});

it('remembers unsuitable classifications without adding headlines', function (): void {
    $commit = Commit::factory()->create(['body' => 'Sunny day']);
    Log::shouldReceive('info')->once()->with('Headline batch completed', [
        'commits' => 1, 'suitable_candidates' => 0, 'linked_existing' => 0, 'new_headlines' => 0,
    ]);

    $this->artisan('commits:link-headlines')->expectsOutputToContain('Processed 1 commits for headlines.')->assertSuccessful();
    $this->artisan('commits:link-headlines')->expectsOutputToContain('Processed 0 commits for headlines.')->assertSuccessful();

    expect($commit->headlines()->count())->toBe(0)
        ->and($commit->refresh()->headlines_classified_at)->not->toBeNull()
        ->and(Headline::query()->count())->toBe(0);
});

it('uses the requested batch size instead of the default', function (): void {
    Commit::factory()->count(5)->create();
    $provider = $this->mock(HeadlineProvider::class);
    $provider->shouldReceive('candidates')->twice()->with(Mockery::on(fn (array $bodies): bool => count($bodies) === 2))->andReturn([]);
    $provider->shouldReceive('candidates')->once()->with(Mockery::on(fn (array $bodies): bool => count($bodies) === 1))->andReturn([]);
    $provider->shouldReceive('matches')->andReturn([]);
    $provider->shouldReceive('generate')->andReturn([]);
    $provider->shouldReceive('deduplicate')->andReturn([]);

    $this->artisan('commits:link-headlines --batch-size=2')->expectsOutputToContain('Processed 5 commits for headlines.')->assertSuccessful();
    $this->artisan('commits:link-headlines --batch-size=2')->expectsOutputToContain('Processed 0 commits for headlines.')->assertSuccessful();
});

it('rejects invalid batch sizes before processing any commits', function (string $batchSize): void {
    $this->mock(HeadlineProvider::class)->shouldNotReceive('candidates');

    $this->artisan('commits:link-headlines', ['--batch-size' => $batchSize])
        ->expectsOutputToContain('The batch size must be an integer between 1 and 100.')->assertFailed();
})->with(['0', '-1', '1.5', 'abc', '101']);
