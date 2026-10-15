<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Jobs\EvaluateCommitJob;
use App\Models\Commit;
use App\Models\ModerationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('evaluates the commit', function (): void {
    config(['services.typesafe.key' => 'test-key', 'services.typesafe.enabled' => true]);

    $spam = ModerationRule::factory()->create();
    $commit = Commit::factory()->pending()->create();

    Http::fake(['api.typesafe.ai/*' => Http::response(jevAnswers(["rule_{$spam->id}" => 0.99]))]);

    dispatch(new EvaluateCommitJob($commit));

    expect($commit->refresh()->status)->toBe(CommitStatus::Rejected)
        ->and($commit->evaluation)->not->toBeNull();
});

it('is unique per commit and dropped when its commit is gone', function (): void {
    $commit = Commit::factory()->pending()->create();
    $job = new EvaluateCommitJob($commit);

    Queue::connection('database')->push($job);

    $payload = json_decode((string) DB::table('jobs')->value('payload'), true);

    expect($job->uniqueId())->toBe($commit->id)
        ->and($payload)
        ->maxTries->toBe(3)
        ->timeout->toBe(60)
        ->deleteWhenMissingModels->toBeTrue();
});
