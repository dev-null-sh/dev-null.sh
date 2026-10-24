<?php

declare(strict_types=1);

use App\Jobs\ClassifyCommitTopics;
use App\Models\Commit;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Classification;
use Laravel\Ai\Responses\Data\BooleanAnswer;

it('queues eligible roots in configurable batches and resumes without repeating completed decisions', function (): void {
    config(['queue.default' => 'database']);
    Classification::fake([
        ['post_1' => new BooleanAnswer(0.9), 'post_2' => new BooleanAnswer(0.1)],
        ['post_1' => new BooleanAnswer(0.8)],
    ]);
    $first = Commit::factory()->create(['body' => 'PHP release planning']);
    $second = Commit::factory()->create(['body' => 'What a beautiful day']);
    $quote = Commit::factory()->create(['body' => 'The repository moved', 'quoted_commit_id' => $first->id]);
    $reply = Commit::factory()->create(['parent_id' => $first->id]);
    $empty = Commit::factory()->create(['body' => '']);
    $deleted = Commit::factory()->create(['deleted_at' => now()]);
    Commit::factory()->create(['topic_decision' => true]);
    Commit::factory()->create(['topic_decision' => false]);

    $this->artisan('commits:classify-topics --batch-size=2')
        ->expectsOutputToContain('Queued 3 commits for topic classification.')
        ->assertSuccessful();

    $this->assertDatabaseCount('jobs', 2);
    $this->assertDatabaseHas('jobs', ['queue' => 'low']);
    Classification::assertNothingClassified();

    for ($batch = 0; $batch < 2; $batch++) {
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'low', '--once' => true])->assertSuccessful();
    }

    expect($first->refresh()->topic_decision)->toBeTrue()
        ->and($second->refresh()->topic_decision)->toBeFalse()
        ->and($quote->refresh()->topic_decision)->toBeTrue()
        ->and($reply->refresh()->topic_decision)->toBeNull()
        ->and($empty->refresh()->topic_decision)->toBeNull()
        ->and($deleted->refresh()->topic_decision)->toBeNull();
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);

    $this->artisan('commits:classify-topics --batch-size=2')
        ->expectsOutputToContain('Queued 0 commits for topic classification.')
        ->assertSuccessful();
    $this->assertDatabaseCount('jobs', 0);
});

it('defaults to batches of twenty and allows single commit and maximum sized batches', function (array $options, int $batchSize): void {
    Queue::fake([ClassifyCommitTopics::class]);
    $commits = Commit::factory()->count(21)->create();

    $this->artisan('commits:classify-topics', $options)
        ->expectsOutputToContain('Queued 21 commits for topic classification.')
        ->assertSuccessful();

    Queue::assertPushed(ClassifyCommitTopics::class, (int) ceil(21 / $batchSize));
    expect(Queue::pushed(ClassifyCommitTopics::class)->flatMap(fn (ClassifyCommitTopics $job): array => $job->commitIds)->all())
        ->toBe($commits->modelKeys());
    Queue::assertPushed(ClassifyCommitTopics::class, fn (ClassifyCommitTopics $job): bool => count($job->commitIds) === min($batchSize, 21));
})->with([
    'default' => [[], 20],
    'single' => [['--batch-size' => 1], 1],
    'maximum' => [['--batch-size' => 100], 100],
]);

it('rejects invalid batch sizes without queueing work', function (string $size): void {
    Queue::fake();

    $this->artisan('commits:classify-topics', ['--batch-size' => $size])
        ->expectsOutputToContain('The batch size must be an integer between 1 and 100.')
        ->assertExitCode(2);

    Queue::assertNothingPushed();
})->with(['0', '-1', '101', '1.5', 'many']);
