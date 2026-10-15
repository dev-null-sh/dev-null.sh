<?php

declare(strict_types=1);

use App\Ai\Agents\TopicExtractor;
use App\Jobs\AssignCommitTopics;
use App\Models\Commit;
use App\Models\Topic;

it('assigns the extracted topics to the commit, reusing existing ones', function (): void {
    TopicExtractor::fake([['topics' => ['laravel', 'rust']]]);
    $existing = Topic::factory()->create(['name' => 'laravel']);
    $commit = Commit::factory()->create(['title' => 'Laravel queues', 'description' => 'Rust driver']);

    dispatch_sync(new AssignCommitTopics($commit));

    expect($commit->topics()->pluck('name')->sort()->values()->all())->toBe(['laravel', 'rust'])
        ->and($commit->topics()->where('name', 'laravel')->sole()->is($existing))->toBeTrue()
        ->and(Topic::query()->count())->toBe(2);

    TopicExtractor::assertPrompted("Laravel queues\n\nRust driver");
});
