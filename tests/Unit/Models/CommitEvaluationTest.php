<?php

declare(strict_types=1);

use App\Enums\EvaluationDecision;
use App\Models\Commit;
use App\Models\CommitEvaluation;

test('to array', function (): void {
    $evaluation = CommitEvaluation::factory()->create()->refresh();

    expect(array_keys($evaluation->toArray()))
        ->toBe([
            'decision',
            'test_decision',
            'results',
            'model',
            'error',
            'created_at',
        ]);
});

it('casts its decisions and results', function (): void {
    $evaluation = CommitEvaluation::factory()->decided(EvaluationDecision::Rejected)->create([
        'results' => [['rule_id' => 'abc', 'probability' => 0.96]],
    ])->refresh();

    expect($evaluation)
        ->decision->toBe(EvaluationDecision::Rejected)
        ->test_decision->toBe(EvaluationDecision::Rejected)
        ->results->toBe([['rule_id' => 'abc', 'probability' => 0.96]])
        ->commit->toBeInstanceOf(Commit::class);
});

it('may have failed without a test decision', function (): void {
    $evaluation = CommitEvaluation::factory()->failed()->create()->refresh();

    expect($evaluation)
        ->decision->toBe(EvaluationDecision::Failed)
        ->test_decision->toBeNull()
        ->error->toBe('Connection timed out.');
});

it('is the latest evaluation of its commit', function (bool $sameTime): void {
    $commit = Commit::factory()->pending()->create();

    CommitEvaluation::factory()->for($commit)->failed()->create([
        'id' => '00000000-0000-4000-8000-000000000001',
    ]);

    if (! $sameTime) {
        $this->travel(1)->minute();
    }

    $latest = CommitEvaluation::factory()->for($commit)->create([
        'id' => '00000000-0000-4000-8000-000000000002',
    ]);

    expect($commit->refresh()->evaluation->id)->toBe($latest->id)
        ->and($commit->fresh('evaluation')->evaluation->id)->toBe($latest->id);
})->with([false, true]);

it('is deleted with its commit', function (): void {
    $evaluation = CommitEvaluation::factory()->create();

    $evaluation->commit->forceDelete();

    expect(CommitEvaluation::query()->count())->toBe(0);
});
