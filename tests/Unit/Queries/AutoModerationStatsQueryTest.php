<?php

declare(strict_types=1);

use App\Enums\EvaluationDecision;
use App\Models\Commit;
use App\Models\CommitEvaluation;
use App\Queries\AutoModerationStatsQuery;

it('counts what the rules did over the last seven days', function (): void {
    CommitEvaluation::factory()->for(Commit::factory()->create())->decided(EvaluationDecision::Approved)->create();
    CommitEvaluation::factory()->for(Commit::factory()->rejected()->create())->decided(EvaluationDecision::Rejected)->create();
    CommitEvaluation::factory()->for(Commit::factory()->rejected()->create())->decided(EvaluationDecision::Rejected)->create();
    CommitEvaluation::factory()->decided(EvaluationDecision::Review)->create();
    CommitEvaluation::factory()->failed()->create();
    CommitEvaluation::factory()->decided(EvaluationDecision::Rejected)->create(['created_at' => now()->subDays(8)]);

    expect(resolve(AutoModerationStatsQuery::class)->get())->toBe([
        'approved' => 1,
        'rejected' => 2,
        'review' => 1,
        'failed' => 1,
        'overturned' => 0,
    ]);
});

it('counts automatic decisions a moderator changed as overturned', function (): void {
    CommitEvaluation::factory()->for(Commit::factory()->rejected()->create())->decided(EvaluationDecision::Approved)->create();
    CommitEvaluation::factory()->for(Commit::factory()->pending()->create())->decided(EvaluationDecision::Rejected)->create();
    CommitEvaluation::factory()->for(Commit::factory()->create())->decided(EvaluationDecision::Review)->create();

    expect(resolve(AutoModerationStatsQuery::class)->get()['overturned'])->toBe(2);
});
