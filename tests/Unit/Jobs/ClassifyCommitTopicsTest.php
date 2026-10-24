<?php

declare(strict_types=1);

use App\Actions\CreateCommit;
use App\Ai\Agents\TopicLabelGenerator;
use App\Enums\CommitStatus;
use App\Exceptions\CommitProcessingFailed;
use App\Jobs\ClassifyCommitTopics;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Classification;
use Laravel\Ai\Prompts\ClassificationPrompt;
use Laravel\Ai\Responses\Data\BooleanAnswer;

it('stores a private yes or no decision without changing moderation', function (float $probability, bool $expected): void {
    TopicLabelGenerator::fake([['post_0' => []]])->preventStrayPrompts();
    Classification::fake([['post_1' => new BooleanAnswer($probability)]]);
    $commit = Commit::factory()->pending()->create(['body' => 'Will PHP 8.6 be released this November?']);

    dispatch_sync(new ClassifyCommitTopics([$commit->id]));

    expect($commit->refresh()->topic_decision)->toBe($expected)
        ->and($commit->status)->toBe(CommitStatus::Pending)
        ->and($commit->toArray())->not->toHaveKey('topic_decision');

    Classification::assertClassified(fn (ClassificationPrompt $prompt): bool => $prompt->state === ['post_1' => $commit->body]
        && $prompt->asks('post_1'));
})->with([[0.49, false], [0.5, true]]);

it('does not classify replies, deleted commits, empty bodies or completed decisions', function (array $attributes): void {
    Classification::fake();
    $commit = Commit::factory()->create($attributes);

    dispatch_sync(new ClassifyCommitTopics([$commit->id]));

    expect($commit->refresh()->topic_decision)->toBe($attributes['topic_decision'] ?? null);
    Classification::assertNothingClassified();
})->with([
    'reply' => fn (): array => ['parent_id' => Commit::factory()->create()->id],
    'deleted' => fn (): array => ['deleted_at' => now()],
    'empty body' => [['body' => '']],
    'positive decision' => fn (): array => ['topic_decision' => true, 'topics_classified_at' => now()],
    'negative decision' => [['topic_decision' => false]],
]);

it('leaves failed classifications undecided so a retry can succeed', function (): void {
    TopicLabelGenerator::fake([['post_0' => []]])->preventStrayPrompts();
    Classification::fake(fn () => throw new RuntimeException('Classifier unavailable'));
    $commit = Commit::factory()->create();

    expect(fn () => dispatch_sync(new ClassifyCommitTopics([$commit->id])))->toThrow(function (CommitProcessingFailed $exception) use ($commit): void {
        expect($exception->getMessage())->toBe('Topic classification failed for commit '.$commit->id.' (RuntimeException, code 0).')
            ->and($exception->getPrevious())->toBeNull();
    });
    expect($commit->refresh()->topic_decision)->toBeNull();

    Classification::fake([['post_1' => new BooleanAnswer(0.9)]]);
    dispatch_sync(new ClassifyCommitTopics([$commit->id]));

    expect($commit->refresh()->topic_decision)->toBeTrue();
});

it('does not treat a missing or invalid provider answer as a negative decision', function (array $answers): void {
    config(['ai.providers.openai.key' => 'sk-test']);
    Http::fake(['api.openai.com/v1/decisions' => Http::response(['answers' => [
        ['type' => 'predicate', 'name' => 'post_1', 'probability' => 0.9],
        ...$answers,
    ]])]);
    $commits = Commit::factory()->count(2)->create();

    expect(fn () => dispatch_sync(new ClassifyCommitTopics($commits->modelKeys())))->toThrow(CommitProcessingFailed::class);
    expect($commits->fresh()->pluck('topic_decision')->all())->toBe([null, null]);
})->with([
    'missing' => [[]],
    'out of range' => [[['type' => 'predicate', 'name' => 'post_2', 'probability' => 1.1]]],
    'wrong type' => [[['type' => 'choice', 'name' => 'post_2', 'choice' => 'yes', 'probabilities' => [['value' => 'yes', 'probability' => 0.9]]]]],
]);

it('creates the commit before classification and keeps failed low priority jobs free of provider content', function (): void {
    config(['queue.default' => 'database', 'ai.providers.openai.key' => 'sk-test']);
    Exceptions::fake();
    Http::fake(['api.openai.com/v1/decisions' => Http::response(['error' => ['message' => 'Sensitive provider response']], 400)]);

    $commit = resolve(CreateCommit::class)->handle(User::factory()->create(), 'Will PHP 8.6 ship this November?');

    $this->assertModelExists($commit);
    $this->assertDatabaseHas('jobs', ['queue' => 'low']);
    Http::assertNothingSent();

    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'default', '--once' => true])->assertSuccessful();
    Http::assertNothingSent();

    foreach ([0, 60, 600] as $delay) {
        $this->travel($delay)->seconds();
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'default,low', '--once' => true])->assertSuccessful();
    }

    expect($commit->refresh())->topic_decision->toBeNull()->status->toBe(CommitStatus::Pending);
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 1);
    expect(DB::table('failed_jobs')->value('exception'))
        ->toContain('Topic classification failed for commit '.$commit->id)
        ->not->toContain('Sensitive provider response', $commit->body);
    Exceptions::assertReported(fn (CommitProcessingFailed $exception): bool => ! $exception->getPrevious() instanceof Throwable
        && ! str_contains($exception->getMessage(), 'Sensitive provider response'));
});

it('does not restore a decision if the commit is deleted during classification', function (): void {
    $commit = Commit::factory()->create();
    Classification::fake(function () use ($commit): array {
        $commit->delete();

        return ['post_1' => new BooleanAnswer(0.9)];
    });

    dispatch_sync(new ClassifyCommitTopics([$commit->id]));

    expect(Commit::withTrashed()->findOrFail($commit->id)->topic_decision)->toBeNull();
});

it('classifies a batch in one request by answer key and skips work completed before execution', function (): void {
    TopicLabelGenerator::fake([['post_0' => []]])->preventStrayPrompts();
    config(['ai.providers.openai.key' => 'sk-test']);
    Http::fake(['api.openai.com/v1/decisions' => Http::response(['answers' => [
        ['type' => 'predicate', 'name' => 'post_2', 'probability' => 0.1],
        ['type' => 'predicate', 'name' => 'post_1', 'probability' => 0.9],
    ]])]);
    $first = Commit::factory()->create(['body' => 'PHP release planning']);
    $second = Commit::factory()->create(['body' => 'What a beautiful day']);
    $completed = Commit::factory()->create();
    $deleted = Commit::factory()->create();
    $removed = Commit::factory()->create();
    $job = new ClassifyCommitTopics([$first->id, $second->id, $completed->id, $deleted->id, $removed->id]);
    $completed->update(['topic_decision' => false]);
    $deleted->delete();
    $removed->forceDelete();

    dispatch_sync($job);
    dispatch_sync($job);

    expect($first->refresh()->topic_decision)->toBeTrue()
        ->and($second->refresh()->topic_decision)->toBeFalse()
        ->and($completed->refresh()->topic_decision)->toBeFalse()
        ->and($deleted->refresh()->topic_decision)->toBeNull();
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => json_decode($request['input'], true) === [
        'post_1' => 'PHP release planning',
        'post_2' => 'What a beautiful day',
    ]);
});

it('does not overwrite a concurrent decision while saving the rest of a batch', function (): void {
    TopicLabelGenerator::fake([['post_0' => []]])->preventStrayPrompts();
    $first = Commit::factory()->create();
    $second = Commit::factory()->create();
    Classification::fake(function () use ($first): array {
        $first->update(['topic_decision' => false]);

        return ['post_1' => new BooleanAnswer(0.9), 'post_2' => new BooleanAnswer(0.9)];
    });

    dispatch_sync(new ClassifyCommitTopics([$first->id, $second->id]));

    expect($first->refresh()->topic_decision)->toBeFalse()
        ->and($second->refresh()->topic_decision)->toBeTrue();
});
