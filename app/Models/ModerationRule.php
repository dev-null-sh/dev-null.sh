<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModerationRuleAction;
use App\Enums\ModerationRuleMode;
use App\Enums\ModerationStrictness;
use App\Enums\RuleOutcome;
use App\Queries\ModerationRuleBuilder;
use Carbon\CarbonInterface;
use Database\Factories\ModerationRuleFactory;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string $matches
 * @property-read string|null $does_not_match
 * @property-read ModerationRuleAction $action
 * @property-read ModerationStrictness $strictness
 * @property-read ModerationRuleMode $mode
 * @property-read string|null $rejection_message
 * @property-read int $position
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[UseEloquentBuilder(ModerationRuleBuilder::class)]
final class ModerationRule extends Model
{
    /** @use HasFactory<ModerationRuleFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'name' => 'string',
            'matches' => 'string',
            'does_not_match' => 'string',
            'action' => ModerationRuleAction::class,
            'strictness' => ModerationStrictness::class,
            'mode' => ModerationRuleMode::class,
            'rejection_message' => 'string',
            'position' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return array{act: int|null, review: int|null}
     */
    public function thresholds(): array
    {
        return $this->strictness->thresholds($this->action);
    }

    /**
     * What this rule does with a commit it matches with the given probability, under its strictness.
     */
    public function outcomeFor(float $probability): RuleOutcome
    {
        $percentage = round($probability * 100, 6);

        ['act' => $act, 'review' => $review] = $this->thresholds();

        if ($act !== null && $percentage >= $act) {
            return $this->action === ModerationRuleAction::Approve ? RuleOutcome::Approve : RuleOutcome::Reject;
        }

        if ($review !== null && $percentage >= $review) {
            return RuleOutcome::Review;
        }

        return RuleOutcome::None;
    }
}
