<?php

declare(strict_types=1);

use App\Ai\Agents\TopicLabelGenerator;
use App\Exceptions\CommitProcessingFailed;
use App\Jobs\AssignCommitTopics;
use App\Jobs\BackfillCommitTopics;
use App\Models\Commit;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;

it('waits for a competing assignment without exhausting retries or advancing the backfill chain', function (): void {
    config(['queue.default' => 'database']);
    $commit = Commit::factory()->create(['topic_decision' => true]);
    TopicLabelGenerator::fake([['post_0' => ['PHP release']]])->preventStrayPrompts();
    $lock = Cache::lock('commit-topic-assignment', 960);
    expect($lock->get())->toBeTrue();
    Bus::chain([new AssignCommitTopics([$commit->id]), new BackfillCommitTopics(20, $commit->id)])->onQueue('low')->dispatch();

    for ($attempt = 0; $attempt < 4; $attempt++) {
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'low', '--once' => true])->assertSuccessful();
        $this->travel(60)->seconds();
    }

    expect($commit->refresh()->topics_classified_at)->toBeNull();
    $this->assertDatabaseCount('jobs', 1);
    $this->assertDatabaseCount('failed_jobs', 0);
    TopicLabelGenerator::assertNeverPrompted();
    $lock->release();

    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'low', '--once' => true])->assertSuccessful();
    expect($commit->refresh()->topics->sole()->value)->toBe('PHP release');
    $this->assertDatabaseCount('jobs', 1);
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'low', '--once' => true])->assertSuccessful();
    $this->assertDatabaseCount('jobs', 0);
});

it('stops after three provider failures without storing provider content in failed jobs', function (): void {
    config(['queue.default' => 'database']);
    Exceptions::fake();
    $commit = Commit::factory()->create(['topic_decision' => true]);
    TopicLabelGenerator::fake(fn () => throw new RuntimeException('Sensitive provider response'));
    dispatch(new AssignCommitTopics([$commit->id]));

    foreach ([0, 60, 600] as $delay) {
        $this->travel($delay)->seconds();
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'low', '--once' => true])->assertSuccessful();
    }

    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 1);
    expect($commit->refresh()->topics_classified_at)->toBeNull()
        ->and(DB::table('failed_jobs')->value('exception'))->toContain('Topic assignment failed')
        ->not->toContain('Sensitive provider response', $commit->body);
    Exceptions::assertReported(CommitProcessingFailed::class);
});
