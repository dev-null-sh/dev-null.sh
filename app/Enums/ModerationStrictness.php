<?php

declare(strict_types=1);

namespace App\Enums;

enum ModerationStrictness: string
{
    case Strict = 'strict';
    case Balanced = 'balanced';
    case Lenient = 'lenient';

    /**
     * Every preset's thresholds for every action, for showing them before a rule is saved.
     *
     * @return array<string, array<string, array{act: int|null, review: int|null}>>
     */
    public static function presets(): array
    {
        $presets = [];

        foreach (self::cases() as $strictness) {
            foreach (ModerationRuleAction::cases() as $action) {
                $presets[$strictness->value][$action->value] = $strictness->thresholds($action);
            }
        }

        return $presets;
    }

    /**
     * The match percentages at which a rule acts on a commit or sends it to a moderator. Reject rules use both, flag
     * rules only send to review, and approve rules only act.
     *
     * @return array{act: int|null, review: int|null}
     */
    public function thresholds(ModerationRuleAction $action): array
    {
        return match ($action) {
            ModerationRuleAction::Reject => match ($this) {
                self::Strict => ['act' => 80, 'review' => 30],
                self::Balanced => ['act' => 90, 'review' => 50],
                self::Lenient => ['act' => 95, 'review' => 70],
            },
            ModerationRuleAction::Review => match ($this) {
                self::Strict => ['act' => null, 'review' => 30],
                self::Balanced => ['act' => null, 'review' => 50],
                self::Lenient => ['act' => null, 'review' => 70],
            },
            ModerationRuleAction::Approve => match ($this) {
                self::Strict => ['act' => 95, 'review' => null],
                self::Balanced => ['act' => 90, 'review' => null],
                self::Lenient => ['act' => 80, 'review' => null],
            },
        };
    }
}
