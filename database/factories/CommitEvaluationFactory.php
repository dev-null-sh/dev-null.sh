<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EvaluationDecision;
use App\Models\Commit;
use App\Models\CommitEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommitEvaluation>
 */
final class CommitEvaluationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commit_id' => Commit::factory()->pending(),
            'decision' => EvaluationDecision::Review,
            'test_decision' => EvaluationDecision::Review,
            'results' => [],
            'model' => 'jev-1.13.0',
            'request_id' => 'req_'.Str::random(32),
            'input_tokens' => 1646,
            'latency_ms' => 274,
            'error' => null,
        ];
    }

    public function decided(EvaluationDecision $decision): self
    {
        return $this->state(fn (array $attributes): array => [
            'decision' => $decision,
            'test_decision' => $decision,
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn (array $attributes): array => [
            'decision' => EvaluationDecision::Failed,
            'test_decision' => null,
            'model' => null,
            'request_id' => null,
            'input_tokens' => null,
            'error' => 'Connection timed out.',
        ]);
    }
}
