<?php

declare(strict_types=1);

use App\Actions\CreateHeadlineSummary;
use App\Contracts\Services\HeadlineProvider;
use App\Enums\CommitStatus;
use App\Exceptions\HeadlineSummaryFailed;
use App\Jobs\GenerateHeadlineSummary;
use App\Models\Commit;
use App\Models\Headline;

it('summarizes only recent public top-level posts with one strongest contribution per author', function (): void {
    $headline = Headline::factory()->create(['name' => 'Vector databases']);
    $strongest = Commit::factory()->hasAttached($headline)->create(['body' => 'Postgres worked well for our vector search.', 'hot_score' => 100]);
    Commit::factory()->for($strongest->user)->hasAttached($headline)->create(['body' => 'Repeated weaker reaction', 'hot_score' => 1]);
    Commit::factory()->hasAttached($headline)->create(['body' => 'We needed a separate index for larger datasets.', 'created_at' => now()->subHours(8)]);
    Commit::factory()->hasAttached($headline)->create(['body' => 'Too old', 'created_at' => now()->subHours(8)->subSecond()]);
    Commit::factory()->hasAttached($headline)->create(['body' => 'In the future', 'created_at' => now()->addSecond()]);
    Commit::factory()->pending()->hasAttached($headline)->create();
    Commit::factory()->rejected()->hasAttached($headline)->create();
    Commit::factory()->hasAttached($headline)->create(['deleted_at' => now()]);
    Commit::factory()->for($strongest, 'parent')->hasAttached($headline)->create();
    Commit::factory()->create(['body' => 'An unrelated topic']);
    $this->mock(HeadlineProvider::class)->shouldReceive('summarize')->once()->with('Vector databases', Mockery::on(
        fn (array $bodies): bool => count($bodies) === 2
            && in_array('Postgres worked well for our vector search.', $bodies, true)
            && in_array('We needed a separate index for larger datasets.', $bodies, true),
    ))->andReturn('Developers compare Postgres with dedicated vector indexes. Larger datasets prompted a separate index.');

    new GenerateHeadlineSummary($headline->id)->handle(resolve(CreateHeadlineSummary::class));

    expect($headline->refresh()->summary)->toBe('Developers compare Postgres with dedicated vector indexes. Larger datasets prompted a separate index.')
        ->and($headline->summary_created_at?->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($headline->toArray())->toBe(['name' => 'Vector databases']);
});

it('bounds the recap to the hundred strongest authors', function (): void {
    $headline = Headline::factory()->create();
    Commit::factory()->count(100)->hasAttached($headline)->create(['body' => 'Useful experience', 'hot_score' => 10]);
    Commit::factory()->hasAttached($headline)->create(['body' => 'Outside the sample', 'hot_score' => 1]);
    $this->mock(HeadlineProvider::class)->shouldReceive('summarize')->once()->with($headline->name, array_fill(0, 100, 'Useful experience'))->andReturn('A useful recap.');

    new GenerateHeadlineSummary($headline->id)->handle(resolve(CreateHeadlineSummary::class));

    expect($headline->refresh()->summary)->toBe('A useful recap.');
});

it('reuses a recap until exactly two hours and then regenerates it', function (): void {
    $headline = Headline::factory()->create();
    Commit::factory()->hasAttached($headline)->create();
    $this->mock(HeadlineProvider::class)->shouldReceive('summarize')->twice()->andReturn('First snapshot.', 'Second snapshot.');
    $job = new GenerateHeadlineSummary($headline->id);
    $action = resolve(CreateHeadlineSummary::class);

    $job->handle($action);
    $this->travel(7199)->seconds();
    $job->handle($action);
    expect($headline->refresh()->summary)->toBe('First snapshot.');

    $this->travel(1)->seconds();
    $job->handle($action);
    expect($headline->refresh()->summary)->toBe('Second snapshot.')
        ->and($headline->summary_created_at?->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('leaves no recap when the posts are insufficient and can reconsider new information', function (): void {
    $headline = Headline::factory()->create();
    Commit::factory()->hasAttached($headline)->create(['body' => 'Interesting!']);
    $this->mock(HeadlineProvider::class)->shouldReceive('summarize')->twice()->andReturn(null, 'A grounded recap.');
    $job = new GenerateHeadlineSummary($headline->id);
    $action = resolve(CreateHeadlineSummary::class);

    $job->handle($action);
    expect($headline->refresh()->summary)->toBeNull()->and($headline->summary_created_at)->toBeNull();

    Commit::factory()->hasAttached($headline)->create(['body' => 'Here are substantive details.']);
    $job->handle($action);
    expect($headline->refresh()->summary)->toBe('A grounded recap.');
});

it('does not call a provider for missing, cold or empty topics', function (string $state): void {
    $headline = Headline::factory()->create();
    match ($state) {
        'missing' => $headline->delete(),
        'cold' => Commit::factory()->hasAttached($headline)->create(['created_at' => now()->subHours(9)]),
        'empty' => Commit::factory()->hasAttached($headline)->create(['body' => '']),
    };
    $this->mock(HeadlineProvider::class)->shouldNotReceive('summarize');

    new GenerateHeadlineSummary($headline->id)->handle(resolve(CreateHeadlineSummary::class));

    expect($headline->fresh()?->summary)->toBeNull();
})->with(['missing', 'cold', 'empty']);

it('discards a generated recap if its source becomes unavailable before publication', function (string $change): void {
    $headline = Headline::factory()->create();
    $commit = Commit::factory()->hasAttached($headline)->create();
    $this->mock(HeadlineProvider::class)->shouldReceive('summarize')->once()->andReturnUsing(function () use ($commit, $headline, $change): string {
        match ($change) {
            'deleted' => $commit->delete(),
            'rejected' => $commit->update(['status' => CommitStatus::Rejected]),
            'detached' => $commit->headlines()->detach(),
            'erased' => $commit->update(['body' => '']),
            'headline removed' => $headline->delete(),
        };

        return 'Must not retain removed content.';
    });

    new GenerateHeadlineSummary($headline->id)->handle(resolve(CreateHeadlineSummary::class));

    expect($headline->fresh()?->summary)->toBeNull();
})->with(['deleted', 'rejected', 'detached', 'erased', 'headline removed']);

it('keeps a fresh recap published while another generator was running', function (): void {
    $headline = Headline::factory()->create();
    Commit::factory()->hasAttached($headline)->create();
    $this->mock(HeadlineProvider::class)->shouldReceive('summarize')->once()->andReturnUsing(function () use ($headline): string {
        $headline->update(['summary' => 'Already published.', 'summary_created_at' => now()]);

        return 'A later competing result.';
    });

    new GenerateHeadlineSummary($headline->id)->handle(resolve(CreateHeadlineSummary::class));

    expect($headline->refresh()->summary)->toBe('Already published.');
});

it('coalesces overlapping generators before calling the provider', function (): void {
    $headline = Headline::factory()->create();
    Commit::factory()->hasAttached($headline)->create();
    $this->mock(HeadlineProvider::class)->shouldReceive('summarize')->once()->andReturnUsing(function (): string {
        Fiber::suspend();

        return 'One shared snapshot.';
    });
    $action = resolve(CreateHeadlineSummary::class);
    $first = new Fiber(fn () => new GenerateHeadlineSummary($headline->id)->handle($action));

    $first->start();
    new GenerateHeadlineSummary($headline->id)->handle($action);
    $first->resume();

    expect($headline->refresh()->summary)->toBe('One shared snapshot.');
});

it('does not copy provider content into queue failures', function (): void {
    $headline = Headline::factory()->create();
    Commit::factory()->hasAttached($headline)->create();
    $this->mock(HeadlineProvider::class)->shouldReceive('summarize')->once()->andThrow(new RuntimeException('Sensitive provider response'));

    expect(fn () => new GenerateHeadlineSummary($headline->id)->handle(resolve(CreateHeadlineSummary::class)))
        ->toThrow(function (HeadlineSummaryFailed $exception) use ($headline): void {
            expect($exception->getMessage())->toBe('Headline summary failed for headline '.$headline->id.' (RuntimeException).')
                ->and($exception->getPrevious())->toBeNull();
        });
    expect($headline->refresh()->summary)->toBeNull();
});
