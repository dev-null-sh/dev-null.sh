<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EvaluationDecision;
use Carbon\CarbonInterface;
use Database\Factories\CommitEvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $commit_id
 * @property-read EvaluationDecision $decision
 * @property-read EvaluationDecision|null $test_decision
 * @property-read list<array{rule_id: string, name: string, action: string, mode: string, probability: float, outcome: string, act_threshold: int|null, review_threshold: int|null}> $results
 * @property-read string|null $model
 * @property-read string|null $request_id
 * @property-read int|null $input_tokens
 * @property-read int|null $latency_ms
 * @property-read string|null $error
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Commit $commit
 */
#[Visible([
    'decision',
    'test_decision',
    'results',
    'model',
    'error',
    'created_at',
])]
final class CommitEvaluation extends Model
{
    /** @use HasFactory<CommitEvaluationFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'commit_id' => 'string',
            'decision' => EvaluationDecision::class,
            'test_decision' => EvaluationDecision::class,
            'results' => 'array',
            'model' => 'string',
            'request_id' => 'string',
            'input_tokens' => 'integer',
            'latency_ms' => 'integer',
            'error' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Commit, $this>
     */
    public function commit(): BelongsTo
    {
        return $this->belongsTo(Commit::class);
    }
}
