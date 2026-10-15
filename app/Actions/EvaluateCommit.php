<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\RuleResult;
use App\Enums\CommitStatus;
use App\Enums\EvaluationDecision;
use App\Models\Commit;
use App\Models\CommitEvaluation;
use App\Models\ModerationRule;
use App\Services\TypeSafe;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final readonly class EvaluateCommit
{
    public function __construct(
        private TypeSafe $typeSafe,
        private RunModerationRules $runModerationRules,
        private DecideModeration $decideModeration,
        private ModerateCommits $moderateCommits,
    ) {
        //
    }

    /**
     * Runs the live and test rules against a pending commit and applies what the live rules decide. When Jev
     * can't answer, the commit simply waits for a moderator, and a moderator's decision always wins.
     */
    public function handle(Commit $commit): ?CommitEvaluation
    {
        if (! $this->typeSafe->isEnabled() || $commit->status !== CommitStatus::Pending || $commit->trashed()) {
            return null;
        }

        $rules = ModerationRule::query()->active()->ordered()->get();

        if ($rules->isEmpty()) {
            return null;
        }

        try {
            $run = $this->runModerationRules->handle($rules, $commit->body, $commit->parent?->body, $commit->linkPreview);
        } catch (ConnectionException|RequestException|UnexpectedValueException $exception) {
            report($exception);

            return CommitEvaluation::query()->create([
                'commit_id' => $commit->id,
                'decision' => EvaluationDecision::Failed,
                'results' => [],
                'error' => $exception->getMessage(),
            ]);
        }

        ['decision' => $decision, 'rule' => $rule] = $this->decideModeration->handle($run->liveResults());

        return DB::transaction(function () use ($commit, $run, $decision, $rule): ?CommitEvaluation {
            if (! Commit::query()->whereKey($commit->id)->pending()->lockForUpdate()->exists()) {
                return null;
            }

            $evaluation = CommitEvaluation::query()->create([
                'commit_id' => $commit->id,
                'decision' => $decision,
                'test_decision' => $this->decideModeration->handle($run->results)['decision'],
                'results' => array_map(fn (RuleResult $result): array => $result->toArray(), $run->results),
                'model' => $run->model,
                'request_id' => $run->requestId,
                'input_tokens' => $run->inputTokens,
                'latency_ms' => $run->latencyMs,
            ]);

            if ($decision === EvaluationDecision::Approved) {
                $this->moderateCommits->handle([$commit->id], CommitStatus::Approved, onlyPending: true);
            }

            if ($decision === EvaluationDecision::Rejected) {
                $this->moderateCommits->handle([$commit->id], CommitStatus::Rejected, $rule?->rejectionMessage, onlyPending: true);
            }

            return $evaluation;
        });
    }
}
