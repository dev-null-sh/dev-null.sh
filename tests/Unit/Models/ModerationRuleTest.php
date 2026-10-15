<?php

declare(strict_types=1);

use App\Enums\ModerationRuleAction;
use App\Enums\ModerationRuleMode;
use App\Enums\ModerationStrictness;
use App\Enums\RuleOutcome;
use App\Models\ModerationRule;

it('casts its action, strictness and mode to enums', function (): void {
    $rule = ModerationRule::factory()->create()->refresh();

    expect($rule)
        ->action->toBe(ModerationRuleAction::Reject)
        ->strictness->toBe(ModerationStrictness::Balanced)
        ->mode->toBe(ModerationRuleMode::Live)
        ->position->toBe(0);
});

it('starts in test mode', function (): void {
    $rule = ModerationRule::query()->create([
        'name' => 'Spam',
        'matches' => 'Advertising.',
        'action' => ModerationRuleAction::Reject,
        'strictness' => ModerationStrictness::Balanced,
        'position' => 0,
    ])->refresh();

    expect($rule->mode)->toBe(ModerationRuleMode::Test);
});

it('has the thresholds of its strictness', function (): void {
    $rule = ModerationRule::factory()->make(['strictness' => ModerationStrictness::Strict]);

    expect($rule->thresholds())->toBe(['act' => 80, 'review' => 30]);
});

it('decides what to do with a match', function (ModerationRuleAction $action, ModerationStrictness $strictness, float $probability, RuleOutcome $outcome): void {
    $rule = ModerationRule::factory()->make(['action' => $action, 'strictness' => $strictness]);

    expect($rule->outcomeFor($probability))->toBe($outcome);
})->with([
    'reject at the act threshold' => [ModerationRuleAction::Reject, ModerationStrictness::Balanced, 0.90, RuleOutcome::Reject],
    'review below the act threshold' => [ModerationRuleAction::Reject, ModerationStrictness::Balanced, 0.89, RuleOutcome::Review],
    'review at the review threshold' => [ModerationRuleAction::Reject, ModerationStrictness::Balanced, 0.50, RuleOutcome::Review],
    'nothing below the review threshold' => [ModerationRuleAction::Reject, ModerationStrictness::Balanced, 0.49, RuleOutcome::None],
    'reject sooner when strict' => [ModerationRuleAction::Reject, ModerationStrictness::Strict, 0.80, RuleOutcome::Reject],
    'review sooner when strict' => [ModerationRuleAction::Reject, ModerationStrictness::Strict, 0.30, RuleOutcome::Review],
    'review later when lenient' => [ModerationRuleAction::Reject, ModerationStrictness::Lenient, 0.94, RuleOutcome::Review],
    'reject later when lenient' => [ModerationRuleAction::Reject, ModerationStrictness::Lenient, 0.95, RuleOutcome::Reject],
    'nothing when lenient' => [ModerationRuleAction::Reject, ModerationStrictness::Lenient, 0.69, RuleOutcome::None],
    'flag at the review threshold' => [ModerationRuleAction::Review, ModerationStrictness::Balanced, 0.50, RuleOutcome::Review],
    'flag even when certain' => [ModerationRuleAction::Review, ModerationStrictness::Balanced, 1.0, RuleOutcome::Review],
    'no flag below the review threshold' => [ModerationRuleAction::Review, ModerationStrictness::Strict, 0.29, RuleOutcome::None],
    'approve at the approve threshold' => [ModerationRuleAction::Approve, ModerationStrictness::Balanced, 0.90, RuleOutcome::Approve],
    'no approval below the approve threshold' => [ModerationRuleAction::Approve, ModerationStrictness::Balanced, 0.89, RuleOutcome::None],
    'approve later when strict' => [ModerationRuleAction::Approve, ModerationStrictness::Strict, 0.94, RuleOutcome::None],
    'approve sooner when lenient' => [ModerationRuleAction::Approve, ModerationStrictness::Lenient, 0.80, RuleOutcome::Approve],
]);

it('compares probabilities without floating point noise', function (): void {
    $rule = ModerationRule::factory()->make(['strictness' => ModerationStrictness::Strict]);

    expect($rule->outcomeFor(0.29 + 0.01))->toBe(RuleOutcome::Review);
});

it('finds the rules in test or live mode in order', function (): void {
    $second = ModerationRule::factory()->testing()->create(['position' => 2]);
    ModerationRule::factory()->off()->create(['position' => 0]);
    $first = ModerationRule::factory()->create(['position' => 1]);

    expect(ModerationRule::query()->active()->ordered()->pluck('id')->all())->toBe([$first->id, $second->id]);
});
