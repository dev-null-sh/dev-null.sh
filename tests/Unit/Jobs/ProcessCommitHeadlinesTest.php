<?php

declare(strict_types=1);

use App\Ai\Agents\CommitHeadlineWriter;
use App\Contracts\Services\HeadlineProvider;
use App\Enums\CommitStatus;
use App\Exceptions\CommitProcessingFailed;
use App\Jobs\ProcessCommitHeadlines;
use App\Models\Commit;
use App\Models\Headline;
use App\Services\HeadlineProviders\OpenAIHeadlineProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Laravel\Ai\Classification;
use Laravel\Ai\Responses\Data\BooleanAnswer;

it('filters a batch, links matches and shares the first of similar generated headlines', function (): void {
    $commits = Commit::factory()->count(4)->sequence(
        ['body' => 'Sunny day'], ['body' => 'Trying pgvector'], ['body' => 'New Laravel AI features'], ['body' => 'Laravel SDK agents'],
    )->create();
    Headline::factory()->create(['name' => 'Vector search']);
    Classification::fake([
        ['post_0' => new BooleanAnswer(0), 'post_1' => new BooleanAnswer(1), 'post_2' => new BooleanAnswer(1), 'post_3' => new BooleanAnswer(1)],
        ['post_1_headline_0' => new BooleanAnswer(1), 'post_2_headline_0' => new BooleanAnswer(0), 'post_3_headline_0' => new BooleanAnswer(0)],
        ['pair_2_3' => new BooleanAnswer(0.98)],
    ])->preventStrayClassifications();
    CommitHeadlineWriter::fake([['post_2' => 'Laravel AI SDK features', 'post_3' => 'Laravel AI agents']])->preventStrayPrompts();
    Log::shouldReceive('info')->once()->with('Headline batch completed', [
        'commits' => 4, 'suitable_candidates' => 3, 'linked_existing' => 1, 'new_headlines' => 1,
    ]);

    new ProcessCommitHeadlines($commits->modelKeys())->handle(new OpenAIHeadlineProvider());

    expect($commits[0]->headlines()->count())->toBe(0)
        ->and(Commit::query()->whereNotNull('headlines_classified_at')->count())->toBe(4)
        ->and($commits[1]->headlines()->sole()->name)->toBe('Vector search')
        ->and($commits[2]->headlines()->sole()->name)->toBe('Laravel AI SDK features')
        ->and($commits[3]->headlines()->sole()->id)->toBe($commits[2]->headlines()->sole()->id)
        ->and(Headline::query()->count())->toBe(2);
    CommitHeadlineWriter::assertPromptedTimes(1);
});

it('rechecks eligibility when a queued batch runs', function (): void {
    $root = Commit::factory()->create();
    $pending = Commit::factory()->pending()->create();
    $rejected = Commit::factory()->create(['status' => CommitStatus::Rejected]);
    $reply = Commit::factory()->for($root, 'parent')->create();
    $empty = Commit::factory()->create(['body' => '']);
    $deleted = Commit::factory()->create();
    $deleted->delete();

    $assigned = Commit::factory()->create();
    $assigned->headlines()->attach(Headline::factory()->create());
    $classified = Commit::factory()->create(['headlines_classified_at' => now()]);
    $provider = $this->mock(HeadlineProvider::class);
    $provider->shouldNotReceive('candidates');

    new ProcessCommitHeadlines([$pending->id, $rejected->id, $reply->id, $empty->id, $deleted->id, $assigned->id, $classified->id])->handle($provider);

    expect(Headline::query()->count())->toBe(1);
});

it('retains matched links if generation fails and skips them on retry', function (): void {
    $matched = Commit::factory()->create(['body' => 'Trying pgvector']);
    $unmatched = Commit::factory()->create(['body' => 'Laravel AI features']);
    $unsuitable = Commit::factory()->create(['body' => 'Sunny day']);
    Headline::factory()->create(['name' => 'Vector search']);
    $provider = $this->mock(HeadlineProvider::class);
    $provider->shouldReceive('candidates')->andReturn([0, 1], [0]);
    $provider->shouldReceive('matches')->andReturn([0 => ['Vector search']], []);
    $provider->shouldReceive('generate')->once()->with([1 => 'Laravel AI features'])->andThrow(new RuntimeException('Sensitive response'));
    $provider->shouldReceive('generate')->once()->with([0 => 'Laravel AI features'])->andReturn([0 => 'Laravel AI SDK']);
    $provider->shouldReceive('deduplicate')->andReturn([0 => 'Laravel AI SDK']);
    $job = new ProcessCommitHeadlines([$matched->id, $unmatched->id, $unsuitable->id]);
    Log::shouldReceive('info')->once()->with('Headline batch completed', [
        'commits' => 1, 'suitable_candidates' => 1, 'linked_existing' => 0, 'new_headlines' => 1,
    ]);

    expect(fn () => $job->handle($provider))->toThrow(CommitProcessingFailed::class);
    expect($unmatched->refresh()->headlines_classified_at)->toBeNull()
        ->and($unsuitable->refresh()->headlines_classified_at)->not->toBeNull();
    $job->handle($provider);

    expect($matched->headlines()->sole()->name)->toBe('Vector search')
        ->and($unmatched->headlines()->sole()->name)->toBe('Laravel AI SDK');
});

it('does not count a stale match as a successful link or mark it classified', function (): void {
    $commit = Commit::factory()->create(['body' => 'Trying pgvector']);
    Headline::factory()->create(['name' => 'Vector search']);
    $provider = $this->mock(HeadlineProvider::class);
    $provider->shouldReceive('candidates')->andReturnUsing(function () use ($commit): array {
        Commit::query()->whereKey($commit->id)->update(['body' => 'Different subject']);

        return [0];
    });
    $provider->shouldReceive('matches')->andReturn([0 => ['Vector search']]);
    $provider->shouldReceive('generate')->with([])->andReturn([]);
    $provider->shouldReceive('deduplicate')->with([])->andReturn([]);
    Log::shouldReceive('info')->once()->with('Headline batch completed', [
        'commits' => 1, 'suitable_candidates' => 1, 'linked_existing' => 0, 'new_headlines' => 0,
    ]);

    new ProcessCommitHeadlines([$commit->id])->handle($provider);

    expect($commit->refresh()->headlines_classified_at)->toBeNull()
        ->and($commit->headlines()->count())->toBe(0);
});

it('queues bounded batches without serializing post bodies', function (): void {
    $commits = Commit::factory()->count(21)->create(['body' => 'A body that must not be queued']);
    $classified = Commit::factory()->create(['headlines_classified_at' => now()]);
    Queue::fake([ProcessCommitHeadlines::class]);

    ProcessCommitHeadlines::dispatchFor(...[...$commits, $classified]);

    Queue::assertPushed(ProcessCommitHeadlines::class, 2);
    Queue::assertPushed(ProcessCommitHeadlines::class, fn (ProcessCommitHeadlines $job): bool => count($job->commitIds) === 20
        && ! str_contains(serialize($job), 'A body that must not be queued'));
});

it('lets an overlapping batch reuse the first workers newly published headline', function (): void {
    $first = Commit::factory()->create(['body' => 'What database do you use for vector search?']);
    $second = Commit::factory()->create(['body' => 'Recommend a database for storing vectors']);
    $firstProvider = Mockery::mock(HeadlineProvider::class);
    $firstProvider->shouldReceive('candidates')->andReturn([0]);
    $firstProvider->shouldReceive('matches')->andReturnUsing(function (): array {
        Fiber::suspend();

        return [];
    });
    $firstProvider->shouldReceive('generate')->andReturn([0 => 'Vector search databases']);
    $firstProvider->shouldReceive('deduplicate')->andReturn([0 => 'Vector search databases']);
    $secondProvider = Mockery::mock(HeadlineProvider::class);
    $secondProvider->shouldReceive('candidates')->andReturn([0]);
    $secondProvider->shouldReceive('matches')->andReturnUsing(fn (array $bodies, array $existing): array => in_array('Vector search databases', $existing, true) ? [0 => ['Vector search databases']] : []);
    $secondProvider->shouldReceive('generate')->andReturnUsing(fn (array $bodies): array => $bodies === [] ? [] : [0 => 'Databases for vector search']);
    $secondProvider->shouldReceive('deduplicate')->andReturnUsing(fn (array $headlines): array => $headlines);
    $worker = new Fiber(fn () => new ProcessCommitHeadlines([$first->id])->handle($firstProvider));
    $worker->start();
    Sleep::whenFakingSleep(function () use ($worker): void {
        if ($worker->isSuspended()) {
            $worker->resume();
        }
    });

    new ProcessCommitHeadlines([$second->id])->handle($secondProvider);
    if ($worker->isSuspended()) {
        $worker->resume();
    }

    expect(Headline::query()->count())->toBe(1)
        ->and($first->headlines()->sole()->name)->toBe('Vector search databases')
        ->and($second->headlines()->sole()->id)->toBe($first->headlines()->sole()->id);
});

it('finishes an in flight new link before pruning evaluates its expiry', function (): void {
    $commit = Commit::factory()->create(['body' => 'Trying pgvector']);
    $headline = Headline::factory()->create(['name' => 'Vector search', 'created_at' => now()->subMonths(2)]);
    $provider = $this->mock(HeadlineProvider::class);
    $provider->shouldReceive('candidates')->andReturn([0]);
    $provider->shouldReceive('matches')->andReturnUsing(function (): array {
        Fiber::suspend();

        return [0 => ['Vector search']];
    });
    $provider->shouldReceive('generate')->with([])->andReturn([]);
    $provider->shouldReceive('deduplicate')->with([])->andReturn([]);
    $worker = new Fiber(fn () => new ProcessCommitHeadlines([$commit->id])->handle($provider));
    $worker->start();
    Sleep::whenFakingSleep(function () use ($worker): void {
        if ($worker->isSuspended()) {
            $worker->resume();
        }
    });

    new Headline()->pruneAll();
    if ($worker->isSuspended()) {
        $worker->resume();
    }

    $this->assertModelExists($headline);
    expect($commit->headlines()->sole()->id)->toBe($headline->id)
        ->and($commit->refresh()->headlines_classified_at)->not->toBeNull();
});

it('discards results after losing its lease and reuses the new owners headline on retry', function (): void {
    $first = Commit::factory()->create(['body' => 'What database do you use for vector search?']);
    $second = Commit::factory()->create(['body' => 'Recommend a database for storing vectors']);
    $firstProvider = Mockery::mock(HeadlineProvider::class);
    $firstProvider->shouldReceive('candidates')->andReturn([0]);
    $firstProvider->shouldReceive('matches')->andReturnUsing(fn (array $bodies, array $existing): array => in_array('Databases for vector search', $existing, true) ? [0 => ['Databases for vector search']] : []);
    $firstProvider->shouldReceive('generate')->andReturnUsing(function (array $bodies): array {
        if ($bodies === []) {
            return [];
        }

        Fiber::suspend();

        return [0 => 'Vector search databases'];
    });
    $firstProvider->shouldReceive('deduplicate')->andReturnUsing(fn (array $headlines): array => $headlines);
    $secondProvider = Mockery::mock(HeadlineProvider::class);
    $secondProvider->shouldReceive('candidates')->andReturn([0]);
    $secondProvider->shouldReceive('matches')->andReturn([]);
    $secondProvider->shouldReceive('generate')->andReturn([0 => 'Databases for vector search']);
    $secondProvider->shouldReceive('deduplicate')->andReturn([0 => 'Databases for vector search']);
    $job = new ProcessCommitHeadlines([$first->id]);
    $worker = new Fiber(fn () => $job->handle($firstProvider));
    $worker->start();
    $this->travel(961)->seconds();

    new ProcessCommitHeadlines([$second->id])->handle($secondProvider);

    expect(fn (): mixed => $worker->resume())->toThrow(CommitProcessingFailed::class);
    expect($first->refresh()->headlines_classified_at)->toBeNull()
        ->and($first->headlines()->count())->toBe(0);

    $job->handle($firstProvider);

    expect(Headline::query()->count())->toBe(1)
        ->and($first->headlines()->sole()->id)->toBe($second->headlines()->sole()->id);
});
