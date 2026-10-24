<?php

declare(strict_types=1);

use App\Ai\Agents\TopicLabelGenerator;
use App\Ai\Agents\TopicMatcher;
use App\Enums\CommitStatus;
use App\Exceptions\CommitProcessingFailed;
use App\Jobs\ClassifyCommitTopics;
use App\Models\Commit;
use App\Models\Topic;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Classification;
use Laravel\Ai\Prompts\ClassificationPrompt;
use Laravel\Ai\Responses\Data\BooleanAnswer;

it('assigns topics only to interesting commits without changing their content or moderation', function (float $probability, array $expected): void {
    TopicLabelGenerator::fake([['post_0' => ['PHP 8.6 release']]])->preventStrayPrompts();
    Classification::fake([['post_1' => new BooleanAnswer($probability)]]);
    $commit = Commit::factory()->create(['body' => 'Will PHP 8.6 be released this November?']);

    dispatch_sync(new ClassifyCommitTopics([$commit->id]));

    expect($commit->refresh()->topics->pluck('value')->all())->toBe($expected)
        ->and($commit->status)->toBe(CommitStatus::Approved)
        ->and($commit->body)->toBe('Will PHP 8.6 be released this November?');

    Classification::assertClassified(fn (ClassificationPrompt $prompt): bool => $prompt->state === ['post_1' => $commit->body]
        && $prompt->asks('post_1'));
})->with([
    'below threshold' => [0.49, []],
    'at threshold' => [0.5, ['PHP 8.6 release']],
]);

it('does not classify unapproved or deleted commits', function (array $attributes): void {
    Classification::fake();
    $commit = Commit::factory()->create($attributes);

    dispatch_sync(new ClassifyCommitTopics([$commit->id]));

    expect($commit->refresh()->topics)->toBeEmpty();
    Classification::assertNothingClassified();
})->with([
    'pending' => [['status' => CommitStatus::Pending]],
    'rejected' => [['status' => CommitStatus::Rejected]],
    'deleted' => fn (): array => ['deleted_at' => now()],
]);

it('does not classify commits that already have a topic association', function (): void {
    $commit = Commit::factory()->hasAttached(Topic::factory())->create();
    $topic = $commit->topics->sole();
    Classification::fake();
    TopicMatcher::fake()->preventStrayPrompts();
    TopicLabelGenerator::fake()->preventStrayPrompts();

    dispatch_sync(new ClassifyCommitTopics([$commit->id]));

    expect($commit->refresh()->topics->sole()->id)->toBe($topic->id);
    Classification::assertNothingClassified();
    TopicMatcher::assertNeverPrompted();
    TopicLabelGenerator::assertNeverPrompted();
});

it('leaves failed classifications unlabeled so a retry can succeed', function (): void {
    TopicLabelGenerator::fake([['post_0' => ['PHP release']]])->preventStrayPrompts();
    Classification::fake(fn () => throw new RuntimeException('Classifier unavailable'));
    $commit = Commit::factory()->create();

    expect(fn () => dispatch_sync(new ClassifyCommitTopics([$commit->id])))->toThrow(function (CommitProcessingFailed $exception) use ($commit): void {
        expect($exception->getMessage())->toBe('Topic classification failed for commit '.$commit->id.' (RuntimeException, code 0).')
            ->and($exception->getPrevious())->toBeNull();
    });
    expect($commit->refresh()->topics)->toBeEmpty();

    Classification::fake([['post_1' => new BooleanAnswer(0.9)]]);
    dispatch_sync(new ClassifyCommitTopics([$commit->id]));

    expect($commit->refresh()->topics->sole()->value)->toBe('PHP release');
});

it('does not treat a missing or invalid provider answer as a negative decision', function (array $answers): void {
    config(['ai.providers.openai.key' => 'sk-test']);
    Http::fake(['api.openai.com/v1/decisions' => Http::response(['answers' => [
        ['type' => 'predicate', 'name' => 'post_1', 'probability' => 0.9],
        ...$answers,
    ]])]);
    $commits = Commit::factory()->count(2)->create();

    expect(fn () => dispatch_sync(new ClassifyCommitTopics($commits->modelKeys())))->toThrow(CommitProcessingFailed::class);
    expect($commits->fresh()->load('topics')->pluck('topics')->flatten())->toBeEmpty();
    $this->assertDatabaseCount('topics', 0);
})->with([
    'missing' => [[]],
    'out of range' => [[['type' => 'predicate', 'name' => 'post_2', 'probability' => 1.1]]],
    'wrong type' => [[['type' => 'choice', 'name' => 'post_2', 'choice' => 'yes', 'probabilities' => [['value' => 'yes', 'probability' => 0.9]]]]],
]);

it('does not assign topics if the commit is deleted during classification', function (): void {
    TopicLabelGenerator::fake([['post_0' => ['PHP release']]])->preventStrayPrompts();
    $commit = Commit::factory()->create();
    Classification::fake(function () use ($commit): array {
        $commit->delete();

        return ['post_1' => new BooleanAnswer(0.9)];
    });

    dispatch_sync(new ClassifyCommitTopics([$commit->id]));

    $this->assertSoftDeleted($commit);
    $this->assertDatabaseCount('topics', 0);
    $this->assertDatabaseCount('commit_topic', 0);
});

it('classifies batches by answer key and retries only commits without associations', function (): void {
    TopicLabelGenerator::fake([['post_0' => ['PHP release']]])->preventStrayPrompts();
    TopicMatcher::fake([['subject_0' => []]])->preventStrayPrompts();
    config(['ai.providers.openai.key' => 'sk-test']);
    Http::fake(['api.openai.com/v1/decisions' => Http::sequence()
        ->push(['answers' => [
            ['type' => 'predicate', 'name' => 'post_2', 'probability' => 0.1],
            ['type' => 'predicate', 'name' => 'post_1', 'probability' => 0.9],
        ]])
        ->push(['answers' => [
            ['type' => 'predicate', 'name' => 'post_1', 'probability' => 0.1],
        ]]),
    ]);
    $first = Commit::factory()->create(['body' => 'PHP release planning']);
    $second = Commit::factory()->create(['body' => 'What a beautiful day']);
    $completed = Commit::factory()->create();
    $deleted = Commit::factory()->create();
    $removed = Commit::factory()->create();
    $job = new ClassifyCommitTopics([$first->id, $second->id, $completed->id, $deleted->id, $removed->id]);
    $topic = Topic::factory()->create();
    $completed->topics()->attach($topic);
    $deleted->delete();
    $removed->forceDelete();

    dispatch_sync($job);
    dispatch_sync($job);

    expect($first->refresh()->topics->sole()->value)->toBe('PHP release')
        ->and($second->refresh()->topics)->toBeEmpty()
        ->and($completed->refresh()->topics->sole()->id)->toBe($topic->id)
        ->and($deleted->refresh()->topics)->toBeEmpty();
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => json_decode($request['input'], true) === [
        'post_1' => 'PHP release planning',
        'post_2' => 'What a beautiful day',
    ]);
    Http::assertSent(fn (Request $request): bool => json_decode($request['input'], true) === [
        'post_1' => 'What a beautiful day',
    ]);
    TopicLabelGenerator::assertPromptedTimes(1);
});

it('preserves a concurrent topic assignment while saving the rest of a batch', function (): void {
    $first = Commit::factory()->create();
    $second = Commit::factory()->create();
    Classification::fake([['post_1' => new BooleanAnswer(0.9), 'post_2' => new BooleanAnswer(0.9)]]);
    TopicLabelGenerator::fake(function () use ($first): array {
        $first->topics()->attach(Topic::factory()->create(['value' => 'Concurrent topic']));

        return ['post_0' => ['Superseded label'], 'post_1' => ['PHP release']];
    });
    TopicMatcher::fake([['subject_1' => []]])->preventStrayPrompts();

    dispatch_sync(new ClassifyCommitTopics([$first->id, $second->id]));

    expect($first->refresh()->topics->sole()->value)->toBe('Concurrent topic')
        ->and($second->refresh()->topics->sole()->value)->toBe('PHP release');
    $this->assertDatabaseMissing('topics', ['value' => 'Superseded label']);
    $this->assertDatabaseCount('commit_topic', 2);
});

it('retries a negative classification and can assign topics on a later invocation', function (): void {
    $commit = Commit::factory()->create();
    Classification::fake([
        ['post_1' => new BooleanAnswer(0.1)],
        ['post_1' => new BooleanAnswer(0.9)],
    ]);
    TopicLabelGenerator::fake([['post_0' => ['PHP release']]])->preventStrayPrompts();

    dispatch_sync(new ClassifyCommitTopics([$commit->id]));

    expect($commit->refresh()->topics)->toBeEmpty();
    TopicLabelGenerator::assertNeverPrompted();

    dispatch_sync(new ClassifyCommitTopics([$commit->id]));

    expect($commit->refresh()->topics->sole()->value)->toBe('PHP release');
    TopicLabelGenerator::assertPromptedTimes(1);
});

it('assigns topics to approved replies', function (): void {
    $reply = Commit::factory()->for(Commit::factory(), 'parent')->create();
    Classification::fake([['post_1' => new BooleanAnswer(0.9)]]);
    TopicLabelGenerator::fake([['post_0' => ['PHP release']]])->preventStrayPrompts();

    dispatch_sync(new ClassifyCommitTopics([$reply->id]));

    expect($reply->refresh()->topics->sole()->value)->toBe('PHP release');
    Classification::assertClassified(fn (ClassificationPrompt $prompt): bool => $prompt->state === ['post_1' => $reply->body]);
});
