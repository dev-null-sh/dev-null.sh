<?php

declare(strict_types=1);

use App\Contracts\Services\HeadlineProvider;
use App\Exceptions\HeadlineSummaryFailed;
use App\Models\Commit;
use App\Models\Headline;

it('immediately replaces fresh hot-topic recaps and generates missing ones without touching cold topics', function (): void {
    $fresh = Headline::factory()->create(['name' => 'Database indexing', 'summary' => 'Previous recap.', 'summary_created_at' => now()->subHour()]);
    $missing = Headline::factory()->create(['name' => 'Editor release']);
    $cold = Headline::factory()->create(['summary' => 'Cold recap.', 'summary_created_at' => now()->subHour()]);
    Commit::factory()->count(2)->hasAttached($fresh)->create(['body' => 'An index made our dashboard faster.']);
    Commit::factory()->count(2)->hasAttached($missing)->create(['body' => 'The editor added keyboard shortcuts.']);
    Commit::factory()->hasAttached($cold)->create(['created_at' => now()->subHours(9)]);
    $provider = $this->mock(HeadlineProvider::class);
    $provider->shouldReceive('summarize')->once()->with('Database indexing', ['An index made our dashboard faster.', 'An index made our dashboard faster.'])->andReturn('An index improved dashboard query performance.');
    $provider->shouldReceive('summarize')->once()->with('Editor release', ['The editor added keyboard shortcuts.', 'The editor added keyboard shortcuts.'])->andReturn('The editor release adds keyboard shortcuts.');

    $this->artisan('headlines:refresh')->expectsOutputToContain('Processed 2 hot topics for recap refresh.')->assertSuccessful();

    expect($fresh->refresh()->summary)->toBe('An index improved dashboard query performance.')
        ->and($fresh->summary_created_at?->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($missing->refresh()->summary)->toBe('The editor release adds keyboard shortcuts.')
        ->and($cold->refresh()->summary)->toBe('Cold recap.');
});

it('retains a fresh recap when no useful replacement can be made', function (): void {
    $headline = Headline::factory()->create(['summary' => 'Previous recap.', 'summary_created_at' => now()->subHour()]);
    Commit::factory()->count(2)->hasAttached($headline)->create();
    $this->mock(HeadlineProvider::class)->shouldReceive('summarize')->once()->andReturnNull();

    $this->artisan('headlines:refresh')->assertSuccessful();

    expect($headline->refresh()->summary)->toBe('Previous recap.')
        ->and($headline->summary_created_at?->toDateTimeString())->toBe(now()->subHour()->toDateTimeString());
});

it('does not publish a refresh over a changed recap or changed sources', function (string $change): void {
    $headline = Headline::factory()->create(['summary' => 'Previous recap.', 'summary_created_at' => now()->subHour()]);
    $source = Commit::factory()->hasAttached($headline)->create();
    Commit::factory()->hasAttached($headline)->create();
    $this->mock(HeadlineProvider::class)->shouldReceive('summarize')->once()->andReturnUsing(function () use ($headline, $source, $change): string {
        match ($change) {
            'recap' => $headline->update(['summary' => 'Newer recap.', 'summary_created_at' => now()]),
            'sources' => $source->update(['body' => 'Changed source material.']),
        };

        return 'A stale replacement.';
    });

    $this->artisan('headlines:refresh')->assertSuccessful();

    expect($headline->refresh()->summary)->toBe($change === 'recap' ? 'Newer recap.' : 'Previous recap.');
})->with(['recap', 'sources']);

it('does no provider work when there are no hot topics', function (): void {
    $this->mock(HeadlineProvider::class)->shouldNotReceive('summarize');

    $this->artisan('headlines:refresh')->expectsOutputToContain('Processed 0 hot topics for recap refresh.')->assertSuccessful();
});

it('sanitizes provider failures and leaves the previous recap intact', function (): void {
    $headline = Headline::factory()->create(['summary' => 'Previous recap.', 'summary_created_at' => now()->subHour()]);
    Commit::factory()->count(2)->hasAttached($headline)->create();
    $this->mock(HeadlineProvider::class)->shouldReceive('summarize')->once()->andThrow(new RuntimeException('Sensitive provider response'));

    expect(fn () => $this->artisan('headlines:refresh')->run())->toThrow(function (HeadlineSummaryFailed $exception) use ($headline): void {
        expect($exception->getMessage())->toBe('Headline summary failed for headline '.$headline->id.' (RuntimeException).')
            ->and($exception->getPrevious())->toBeNull();
    });
    expect($headline->refresh()->summary)->toBe('Previous recap.');
});
