<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\UserTimeout;
use App\Queries\ModerationBacklogQuery;

it('has nothing waiting on a moderator', function (): void {
    expect(resolve(ModerationBacklogQuery::class)->get())->toBe([
        'pending' => 0,
        'oldest_pending_age' => null,
        'open_reports' => 0,
        'active_timeouts' => 0,
    ]);
});

it('counts the pending commits, how long the oldest waited, the open reports and the timeouts still running', function (): void {
    Commit::factory()->pending()->create(['created_at' => now()->subHours(2)]);
    Commit::factory()->pending()->create(['created_at' => now()->subMinutes(5)]);
    Commit::factory()->pending()->create(['created_at' => now()->subDay()])->delete();
    Commit::factory()->create(['created_at' => now()->subDays(2)]);

    CommitReport::factory()->create();
    CommitReport::factory()->create()->forceFill(['dismissed_at' => now()])->save();
    CommitReport::factory()->for(Commit::factory()->rejected())->create();

    UserTimeout::factory()->create();
    UserTimeout::factory()->ended()->create();

    expect(resolve(ModerationBacklogQuery::class)->get())->toBe([
        'pending' => 2,
        'oldest_pending_age' => 7200,
        'open_reports' => 1,
        'active_timeouts' => 1,
    ]);
});
