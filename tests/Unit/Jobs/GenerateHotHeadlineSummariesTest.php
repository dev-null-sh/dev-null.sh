<?php

declare(strict_types=1);

use App\Actions\DeleteExpiredHeadlineSummaries;
use App\Jobs\GenerateHeadlineSummary;
use App\Jobs\GenerateHotHeadlineSummaries;
use App\Models\Commit;
use App\Models\Headline;
use App\Queries\HotHeadlinesQuery;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\Queue;

it('expires old snapshots and queues only hot topics without a fresh recap', function (): void {
    $headlines = Headline::factory()->count(8)
        ->sequence(fn (Sequence $sequence): array => ['name' => 'Topic '.$sequence->index])
        ->create();
    foreach ($headlines as $headline) {
        Commit::factory()->hasAttached($headline)->create();
    }

    $fresh = $headlines->first();
    $expired = $headlines[4];
    $fresh->update(['summary' => 'Still fresh.', 'summary_created_at' => now()->subHours(2)->addSecond()]);
    $expired->update(['summary' => 'Old hot snapshot.', 'summary_created_at' => now()->subHours(2)]);
    $cold = Headline::factory()->create(['summary' => 'Old cold snapshot.', 'summary_created_at' => now()->subHours(3)]);
    Queue::fake([GenerateHeadlineSummary::class]);

    new GenerateHotHeadlineSummaries()->handle(resolve(HotHeadlinesQuery::class), resolve(DeleteExpiredHeadlineSummaries::class));

    Queue::assertPushed(GenerateHeadlineSummary::class, 4);
    Queue::assertNotPushed(GenerateHeadlineSummary::class, fn (GenerateHeadlineSummary $job): bool => $job->headlineId === $fresh->id || $job->headlineId === $cold->id || $job->headlineId === $headlines->last()->id);
    Queue::assertPushed(GenerateHeadlineSummary::class, fn (GenerateHeadlineSummary $job): bool => $job->uniqueId() === $expired->id);
    expect($fresh->refresh()->summary)->toBe('Still fresh.')
        ->and($expired->refresh()->summary)->toBeNull()
        ->and($expired->summary_created_at)->toBeNull()
        ->and($cold->refresh()->summary)->toBeNull()
        ->and($cold->summary_created_at)->toBeNull();
});
