<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\ExploreTopic;
use App\Queries\ExploreBatchQuery;
use App\Services\ExploreBatch;
use App\Services\OpenAI;
use Illuminate\Support\Facades\DB;

function exploreBatchTopic(string $key, string $kind = 'subject'): ExploreTopic
{
    return ExploreTopic::query()->create(['kind' => $kind, 'key' => $key, 'title' => $key, 'categories' => []]);
}

function exploreBatchMember(ExploreTopic $topic, array $attributes): Commit
{
    $commit = Commit::factory()->create($attributes);
    DB::table('explore_commit_topic')->insert(['topic_id' => $topic->id, 'commit_id' => $commit->id]);

    return $commit;
}

it('keeps the oldest seed and every distant commit inside its claimed cohort [EXP-04 EXP-05]', function (): void {
    config(['explore.request_size' => 10]);
    $seed = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $distant = Commit::factory()->create(['embedding' => embeddingVector(1)]);
    $closest = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $outside = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $nextTie = Commit::factory()->create(['embedding' => embeddingVector(1)]);
    $waiting = collect([$seed, $nextTie, $distant, $closest]);
    $closest->forceFill(['body' => 'Retain the supplied source text.']);

    $selected = resolve(ExploreBatchQuery::class)->commits($waiting);

    expect($selected->pluck('id')->all())->toBe([$seed->id, $closest->id, $nextTie->id, $distant->id]);
    expect($selected->pluck('id'))->not->toContain($outside->id);
    expect($selected[1])->toBe($closest);
    expect(resolve(ExploreBatch::class)->pack($waiting)['prompt'])->toContain('Retain the supplied source text.');
});

it('preserves cohort order for equal distances including missing zero and opposite vectors [EXP-05]', function (): void {
    config(['explore.request_size' => 10]);
    $seed = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $missing = Commit::factory()->create(['embedding' => null]);
    $opposite = Commit::factory()->create(['embedding' => array_map(fn (float $value): float => -$value, embeddingVector(0))]);
    $zero = Commit::factory()->create(['body' => 'Vector without direction', 'embedding' => array_fill(0, OpenAI::EMBEDDING_DIMENSIONS, 0.0)]);
    $firstTie = Commit::factory()->create(['embedding' => embeddingVector(1)]);
    $secondTie = Commit::factory()->create(['embedding' => embeddingVector(2)]);

    $selected = resolve(ExploreBatchQuery::class)->commits(collect([$seed, $missing, $opposite, $zero, $secondTie, $firstTie]));

    expect($selected->pluck('id')->all())->toBe([$seed->id, $secondTie->id, $firstTie->id, $missing->id, $opposite->id, $zero->id]);
});

it('keeps chronological order when semantic ordering is disabled or the seed has no direction [EXP-05]', function (?array $vector, bool $semantic): void {
    config(['explore.request_size' => 2]);
    $seed = Commit::factory()->create(['embedding' => $vector]);
    $next = Commit::factory()->create(['embedding' => embeddingVector(1)]);
    $closest = Commit::factory()->create(['embedding' => embeddingVector(0)]);

    expect(resolve(ExploreBatchQuery::class)->commits(collect([$seed, $next, $closest]), $semantic)->pluck('id')->all())
        ->toBe([$seed->id, $next->id]);
})->with([
    'disabled' => [embeddingVector(0), false],
    'missing seed' => [null, true],
    'zero seed' => [array_fill(0, OpenAI::EMBEDDING_DIMENSIONS, 0.0), true],
]);

it('handles empty cohorts single commits and single-commit requests [EXP-05]', function (): void {
    $query = resolve(ExploreBatchQuery::class);
    expect($query->commits(collect()))->toBeEmpty();
    $seed = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    expect($query->commits(collect([$seed]))->pluck('id')->all())->toBe([$seed->id]);
    config(['explore.request_size' => 1]);
    $other = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    expect($query->commits(collect([$seed, $other]))->pluck('id')->all())->toBe([$seed->id]);
    expect(fn () => resolve(ExploreBatch::class)->pack(collect()))->toThrow(RuntimeException::class, 'Explore batch is empty.');
});

it('uses average member vectors and the closest commit rather than the batch average [EXP-02 EXP-05]', function (): void {
    config(['explore.catalog_size' => 1]);
    $first = Commit::factory()->create(['body' => 'First input', 'embedding' => embeddingVector(0)]);
    $second = Commit::factory()->create(['body' => 'Second input', 'embedding' => embeddingVector(1)]);
    $averaged = exploreBatchTopic('averaged');
    exploreBatchMember($averaged, ['embedding' => embeddingVector(0)]);
    exploreBatchMember($averaged, ['embedding' => embeddingVector(1)]);
    $exact = exploreBatchTopic('exact');
    exploreBatchMember($exact, ['embedding' => embeddingVector(1)]);
    $story = exploreBatchTopic('story', 'story');
    exploreBatchMember($story, ['embedding' => embeddingVector(0)]);

    $topics = resolve(ExploreBatchQuery::class)->topics(collect([$first, $second]));

    expect($topics->pluck('id')->all())->toBe([$exact->id, $story->id]);
});

it('preserves Unicode literal title boosts and stable topic ties without treating text as SQL [EXP-05]', function (): void {
    $tie = exploreBatchTopic('first-tie');
    $match = exploreBatchTopic('laravel');
    $match->update(['title' => "LÄRAVEL '); DROP TABLE commits; -- %_"]);

    $otherTie = exploreBatchTopic('second-tie');
    foreach ([$tie, $match, $otherTie] as $topic) {
        exploreBatchMember($topic, ['embedding' => embeddingVector(0)]);
    }

    $input = Commit::factory()->create(['body' => "Discussing läravel '); drop table commits; -- %_ today", 'embedding' => embeddingVector(0)]);
    $topics = resolve(ExploreBatchQuery::class)->topics(collect([$input]));

    expect($topics->pluck('id')->all())->toBe([$match->id, $tie->id, $otherTie->id]);
    expect(Commit::query()->count())->toBe(4);
});

it('ignores old rejected and deleted members and stale or suppressed topics [EXP-05 EXP-09]', function (): void {
    $input = Commit::factory()->create(['body' => 'Input text', 'embedding' => embeddingVector(0)]);
    $clean = exploreBatchTopic('clean');
    exploreBatchMember($clean, ['embedding' => embeddingVector(0)]);
    exploreBatchMember($clean, ['embedding' => embeddingVector(1), 'status' => CommitStatus::Rejected]);
    exploreBatchMember($clean, ['embedding' => embeddingVector(1), 'status' => CommitStatus::Pending]);
    exploreBatchMember($clean, ['embedding' => embeddingVector(1), 'deleted_at' => now()]);
    exploreBatchMember($clean, ['embedding' => embeddingVector(1), 'created_at' => now()->subDays(8)]);
    $mixed = exploreBatchTopic('mixed');
    exploreBatchMember($mixed, ['embedding' => embeddingVector(0)]);
    exploreBatchMember($mixed, ['embedding' => embeddingVector(1)]);
    $suppressed = exploreBatchTopic('suppressed');
    $suppressed->update(['suppressed_at' => now()]);

    $stale = exploreBatchTopic('stale');
    $stale->update(['updated_at' => now()->subDays(8)]);

    expect(resolve(ExploreBatchQuery::class)->topics(collect([$input]))->pluck('id')->all())->toBe([$clean->id, $mixed->id]);
});

it('keeps missing and zero topic centroids at distance two with separate kind limits [EXP-05]', function (): void {
    config(['explore.catalog_size' => 1]);
    $input = Commit::factory()->create(['body' => 'Input text', 'embedding' => embeddingVector(0)]);
    $missing = exploreBatchTopic('missing');
    $zero = exploreBatchTopic('zero', 'story');
    exploreBatchMember($zero, ['embedding' => array_fill(0, OpenAI::EMBEDDING_DIMENSIONS, 0.0)]);
    $cancelled = exploreBatchTopic('cancelled');
    exploreBatchMember($cancelled, ['embedding' => embeddingVector(0)]);
    exploreBatchMember($cancelled, ['embedding' => array_map(fn (float $value): float => -$value, embeddingVector(0))]);
    $opposite = exploreBatchTopic('opposite');
    exploreBatchMember($opposite, ['embedding' => array_map(fn (float $value): float => -$value, embeddingVector(0))]);

    expect(resolve(ExploreBatchQuery::class)->topics(collect([$input]))->pluck('id')->all())->toBe([$missing->id, $zero->id]);
});

it('ranks the catalogue by title boost when every input vector is unusable [EXP-05]', function (): void {
    $first = exploreBatchTopic('other');
    $match = exploreBatchTopic('laravel');
    $missing = Commit::factory()->create(['body' => 'Discussing Laravel', 'embedding' => null]);
    $zero = Commit::factory()->create(['body' => 'Vector without direction', 'embedding' => array_fill(0, OpenAI::EMBEDDING_DIMENSIONS, 0.0)]);

    expect(resolve(ExploreBatchQuery::class)->topics(collect([$missing, $zero]))->pluck('id')->all())->toBe([$match->id, $first->id]);
    expect(resolve(ExploreBatchQuery::class)->topics(collect())->pluck('id')->all())->toBe([$first->id, $match->id]);
});

it('returns an empty catalogue when no topics are available [EXP-05]', function (): void {
    expect(resolve(ExploreBatchQuery::class)->topics(collect()))->toBeEmpty();
});
