<?php

declare(strict_types=1);

use App\Actions\UpdateCommitTopics;
use App\Ai\Agents\TopicLabelGenerator;
use App\Ai\Agents\TopicMatcher;
use App\Enums\CommitStatus;
use App\Exceptions\CommitProcessingFailed;
use App\Jobs\ClassifyCommitTopics;
use App\Models\Commit;
use App\Models\Topic;
use Laravel\Ai\Classification;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\BooleanAnswer;

it('reuses multiple confident existing topics without generating labels or refreshing unrelated topics', function (): void {
    $release = Topic::factory()->hasAttached(Commit::factory())->create(['value' => 'PHP 8.6 release schedule', 'last_applied_at' => now()->subWeek()]);
    $stream = Topic::factory()->hasAttached(Commit::factory())->create(['value' => '/dev/null livestream', 'last_applied_at' => now()->subWeek()]);
    $unrelated = Topic::factory()->hasAttached(Commit::factory())->create(['last_applied_at' => now()->subWeek()]);
    $commit = Commit::factory()->create(['topic_decision' => true]);
    TopicMatcher::fake([['subject_0' => [
        ['topic' => 0, 'confidence' => 0.85],
        ['topic' => 1, 'confidence' => 0.99],
        ['topic' => 2, 'confidence' => 0.849],
    ]]])->preventStrayPrompts();
    TopicLabelGenerator::fake()->preventStrayPrompts();

    resolve(UpdateCommitTopics::class)->handle([$commit->id]);

    expect($commit->topics()->pluck('topics.id')->all())->toEqualCanonicalizing([$release->id, $stream->id]);
    expect($release->refresh()->last_applied_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($stream->refresh()->last_applied_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($unrelated->refresh()->last_applied_at->toDateTimeString())->toBe(now()->subWeek()->toDateTimeString());
    TopicLabelGenerator::assertNeverPrompted();
});

it('deduplicates batch labels to their first wording without merging merely transitive matches', function (): void {
    $first = Commit::factory()->create(['body' => 'PHP 8.6 release date?', 'topic_decision' => true]);
    $second = Commit::factory()->create(['body' => 'PHP 8.6 schedule and the livestream', 'topic_decision' => true]);
    $third = Commit::factory()->create(['body' => 'PHP 8.7 release date?', 'topic_decision' => true]);
    TopicLabelGenerator::fake([[
        'post_2' => ['PHP 8.7 release'],
        'post_1' => ['PHP 8.6 timing', '/dev/null livestream'],
        'post_0' => ['PHP 8.6 release schedule'],
    ]])->preventStrayPrompts();
    TopicMatcher::fake([[
        'subject_1' => [['topic' => 0, 'confidence' => 0.95]],
        'subject_2' => [],
        'subject_3' => [['topic' => 1, 'confidence' => 0.9]],
    ]])->preventStrayPrompts();

    resolve(UpdateCommitTopics::class)->handle([$third->id, $second->id, $first->id]);

    expect($first->topics->sole()->value)->toBe('PHP 8.6 release schedule')
        ->and($second->topics->pluck('value')->all())->toEqualCanonicalizing(['PHP 8.6 release schedule', '/dev/null livestream'])
        ->and($third->topics->sole()->value)->toBe('PHP 8.7 release');
    $this->assertDatabaseCount('topics', 3);
    $this->assertDatabaseCount('commit_topic', 4);
    expect($first->refresh()->topics_classified_at)->not->toBeNull();
    TopicMatcher::assertPromptedTimes(1);
});

it('generates only for unmatched posts and keeps their original positions', function (): void {
    $topic = Topic::factory()->hasAttached(Commit::factory())->create(['value' => 'Vite release']);
    $matched = Commit::factory()->create(['topic_decision' => true]);
    $unmatched = Commit::factory()->create(['body' => 'PHP 8.6 release', 'topic_decision' => true]);
    TopicMatcher::fake([['subject_0' => [['topic' => 0, 'confidence' => 1]], 'subject_1' => []]])->preventStrayPrompts();
    TopicLabelGenerator::fake([['post_1' => ['PHP 8.6 release']]])->preventStrayPrompts();

    resolve(UpdateCommitTopics::class)->handle([$matched->id, $unmatched->id]);

    expect($matched->topics->sole()->id)->toBe($topic->id)
        ->and($unmatched->topics->sole()->value)->toBe('PHP 8.6 release');
    TopicLabelGenerator::assertPrompted(fn (AgentPrompt $prompt): bool => json_decode($prompt->prompt, true) === ['post_1' => $unmatched->body]);
});

it('does not revive expired associations when the same label is generated again', function (): void {
    $expired = Topic::factory()->create(['value' => 'PHP release', 'last_applied_at' => now()->subMonthNoOverflow()->subSecond()]);
    $old = Commit::factory()->create(['topic_decision' => true, 'topics_classified_at' => now()->subMonths(2)]);
    $old->topics()->attach($expired);
    $fresh = Commit::factory()->create(['topic_decision' => true]);
    TopicLabelGenerator::fake([['post_0' => ['PHP release']]])->preventStrayPrompts();
    Classification::fake();

    resolve(UpdateCommitTopics::class)->handle([$fresh->id]);

    $this->assertModelMissing($expired);
    expect($old->topics()->count())->toBe(0)
        ->and($fresh->topics->sole()->value)->toBe('PHP release')
        ->and($fresh->topics->sole()->id)->not->toBe($expired->id);
    Classification::assertNothingClassified();
});

it('deduplicates exact labels case insensitively even if the semantic classifier misses them', function (): void {
    $topic = Topic::factory()->hasAttached(Commit::factory())->create(['value' => 'PHP release']);
    $commit = Commit::factory()->create(['topic_decision' => true]);
    TopicMatcher::fake([['subject_0' => []]])->preventStrayPrompts();
    TopicLabelGenerator::fake([['post_0' => ['php RELEASE']]])->preventStrayPrompts();

    resolve(UpdateCommitTopics::class)->handle([$commit->id]);

    expect($commit->topics->sole()->id)->toBe($topic->id);
    $this->assertDatabaseCount('topics', 1);
});

it('does not repeat successful assignments or empty generation results', function (): void {
    $commit = Commit::factory()->create(['topic_decision' => true]);
    TopicLabelGenerator::fake([['post_0' => []]])->preventStrayPrompts();

    resolve(UpdateCommitTopics::class)->handle([$commit->id]);
    $completedAt = $commit->refresh()->topics_classified_at;
    $this->travel(1)->day();
    resolve(UpdateCommitTopics::class)->handle([$commit->id]);

    expect($commit->refresh()->topics_classified_at->equalTo($completedAt))->toBeTrue();
    $this->assertDatabaseCount('topics', 0);
});

it('does not attach generated labels to posts deleted during the provider request', function (): void {
    $commit = Commit::factory()->create(['topic_decision' => true]);
    TopicLabelGenerator::fake(function () use ($commit): array {
        $commit->delete();

        return ['post_0' => ['PHP release']];
    });

    resolve(UpdateCommitTopics::class)->handle([$commit->id]);

    $this->assertDatabaseCount('topics', 0);
    $this->assertDatabaseCount('commit_topic', 0);
    expect($commit->refresh()->topics_classified_at)->toBeNull();
});

it('retries label generation without repeating the interesting decision or retaining provider content', function (): void {
    $commit = Commit::factory()->create();
    Classification::fake([['post_1' => new BooleanAnswer(0.9)]]);
    TopicLabelGenerator::fake(fn () => throw new RuntimeException('Private response'));

    expect(fn () => dispatch_sync(new ClassifyCommitTopics([$commit->id])))->toThrow(function (CommitProcessingFailed $exception): void {
        expect($exception->getMessage())->not->toContain('Private response')
            ->and($exception->getPrevious())->toBeNull();
    });
    expect($commit->refresh()->topic_decision)->toBeTrue()
        ->and($commit->topics_classified_at)->toBeNull();
    $this->assertDatabaseCount('topics', 0);

    Classification::fake(fn () => throw new LogicException('Unexpected second classification'));
    TopicLabelGenerator::fake([['post_0' => ['PHP release']]])->preventStrayPrompts();
    dispatch_sync(new ClassifyCommitTopics([$commit->id]));

    expect($commit->refresh()->topics->sole()->value)->toBe('PHP release');
});

it('leaves an entire assignment batch untouched when a later match answer is invalid', function (): void {
    Topic::factory()->hasAttached(Commit::factory())->create();
    $commits = Commit::factory()->count(2)->create(['topic_decision' => true]);
    TopicMatcher::fake([[
        'subject_0' => [['topic' => 0, 'confidence' => 1]],
        'subject_1' => [['topic' => 999, 'confidence' => 1]],
    ]])->preventStrayPrompts();

    expect(fn () => dispatch_sync(new ClassifyCommitTopics($commits->modelKeys())))->toThrow(CommitProcessingFailed::class);

    expect($commits->fresh()->pluck('topics_classified_at')->all())->toBe([null, null]);
    $this->assertDatabaseCount('commit_topic', 1);
});

it('bounds matching requests without dropping candidates in later chunks', function (): void {
    $topics = Topic::factory()->hasAttached(Commit::factory())->count(101)->create();
    $commit = Commit::factory()->create(['topic_decision' => true]);
    TopicMatcher::fake([
        ['subject_0' => []],
        ['subject_0' => [['topic' => 100, 'confidence' => 0.95]]],
    ])->preventStrayPrompts();
    TopicLabelGenerator::fake()->preventStrayPrompts();

    resolve(UpdateCommitTopics::class)->handle([$commit->id]);

    expect($commit->topics->sole()->id)->toBe($topics->last()->id);
    TopicMatcher::assertPromptedTimes(2);
    TopicLabelGenerator::assertNeverPrompted();
});

it('does not generate or share labels from unapproved posts in a mixed author batch', function (): void {
    $pending = Commit::factory()->pending()->create(['topic_decision' => true]);
    $rejected = Commit::factory()->rejected()->create(['topic_decision' => true]);
    $approved = Commit::factory()->create(['body' => 'PHP release date', 'topic_decision' => true]);
    TopicLabelGenerator::fake([['post_0' => ['PHP release']]])->preventStrayPrompts();
    TopicMatcher::fake()->preventStrayPrompts();

    resolve(UpdateCommitTopics::class)->handle([$pending->id, $rejected->id, $approved->id]);

    expect($pending->refresh()->topics_classified_at)->toBeNull()
        ->and($rejected->refresh()->topics_classified_at)->toBeNull()
        ->and($approved->topics->sole()->value)->toBe('PHP release');
    TopicLabelGenerator::assertPrompted(fn (AgentPrompt $prompt): bool => json_decode($prompt->prompt, true) === ['post_0' => $approved->body]);
    TopicMatcher::assertNeverPrompted();
});

it('does not apply labels to a post rejected while generation is running', function (): void {
    $commit = Commit::factory()->create(['topic_decision' => true]);
    TopicLabelGenerator::fake(function () use ($commit): array {
        $commit->update(['status' => CommitStatus::Rejected]);

        return ['post_0' => ['PHP release']];
    });

    resolve(UpdateCommitTopics::class)->handle([$commit->id]);

    expect($commit->refresh()->topics_classified_at)->toBeNull();
    $this->assertDatabaseCount('topics', 0);
});
