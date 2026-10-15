<?php

declare(strict_types=1);

use App\Enums\ModerationRuleAction;
use App\Enums\ModerationStrictness;

it('has the strictness presets of a moderation rule', function (): void {
    expect(array_column(ModerationStrictness::cases(), 'value', 'name'))->toBe([
        'Strict' => 'strict',
        'Balanced' => 'balanced',
        'Lenient' => 'lenient',
    ]);
});

it('has the thresholds of each preset', function (ModerationStrictness $strictness, ModerationRuleAction $action, array $thresholds): void {
    expect($strictness->thresholds($action))->toBe($thresholds);
})->with([
    [ModerationStrictness::Strict, ModerationRuleAction::Reject, ['act' => 80, 'review' => 30]],
    [ModerationStrictness::Balanced, ModerationRuleAction::Reject, ['act' => 90, 'review' => 50]],
    [ModerationStrictness::Lenient, ModerationRuleAction::Reject, ['act' => 95, 'review' => 70]],
    [ModerationStrictness::Strict, ModerationRuleAction::Review, ['act' => null, 'review' => 30]],
    [ModerationStrictness::Balanced, ModerationRuleAction::Review, ['act' => null, 'review' => 50]],
    [ModerationStrictness::Lenient, ModerationRuleAction::Review, ['act' => null, 'review' => 70]],
    [ModerationStrictness::Strict, ModerationRuleAction::Approve, ['act' => 95, 'review' => null]],
    [ModerationStrictness::Balanced, ModerationRuleAction::Approve, ['act' => 90, 'review' => null]],
    [ModerationStrictness::Lenient, ModerationRuleAction::Approve, ['act' => 80, 'review' => null]],
]);

it('lists the thresholds of every preset and action', function (): void {
    expect(ModerationStrictness::presets())
        ->toHaveKeys(['strict', 'balanced', 'lenient'])
        ->and(ModerationStrictness::presets()['strict'])->toBe([
            'reject' => ['act' => 80, 'review' => 30],
            'review' => ['act' => null, 'review' => 30],
            'approve' => ['act' => 95, 'review' => null],
        ]);
});
