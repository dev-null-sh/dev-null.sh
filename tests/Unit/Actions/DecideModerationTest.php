<?php

declare(strict_types=1);

use App\Actions\DecideModeration;
use App\Dto\RuleResult;
use App\Enums\EvaluationDecision;
use App\Enums\ModerationStrictness;
use App\Models\ModerationRule;

function ruleResult(string $state, float $probability, string $name = 'Rule', ModerationStrictness $strictness = ModerationStrictness::Balanced): RuleResult
{
    $factory = ModerationRule::factory();

    if ($state !== 'rejecting') {
        $factory = $factory->{$state}();
    }

    return RuleResult::for($factory->create(['name' => $name, 'strictness' => $strictness]), $probability);
}

it('rejects when a reject rule is sure, with the most certain one', function (): void {
    $spam = ruleResult('rejecting', 0.92, 'Spam');
    $scam = ruleResult('rejecting', 0.98, 'Scam');

    $decision = resolve(DecideModeration::class)->handle([$spam, $scam, ruleResult('approving', 0.99)]);

    expect($decision['decision'])->toBe(EvaluationDecision::Rejected)
        ->and($decision['rule'])->toBe($scam);
});

it('rejects over a review hit', function (): void {
    $spam = ruleResult('rejecting', 0.95, 'Spam');

    $decision = resolve(DecideModeration::class)->handle([ruleResult('flagging', 0.99), $spam]);

    expect($decision)->toBe(['decision' => EvaluationDecision::Rejected, 'rule' => $spam]);
});

it('sends to review when a rule is unsure', function (): void {
    $harassment = ruleResult('rejecting', 0.69, 'Harassment');

    $decision = resolve(DecideModeration::class)->handle([$harassment, ruleResult('approving', 0.97)]);

    expect($decision)->toBe(['decision' => EvaluationDecision::Review, 'rule' => $harassment]);
});

it('sends to review when a flag rule fires, even when an approve rule passes', function (): void {
    $offTopic = ruleResult('flagging', 0.98, 'Off-topic');

    $decision = resolve(DecideModeration::class)->handle([$offTopic, ruleResult('approving', 0.97)]);

    expect($decision)->toBe(['decision' => EvaluationDecision::Review, 'rule' => $offTopic]);
});

it('approves when every approve rule is sure and nothing else fired', function (): void {
    $friendly = ruleResult('approving', 0.97, 'Friendly');

    $decision = resolve(DecideModeration::class)->handle([
        ruleResult('rejecting', 0.09),
        ruleResult('flagging', 0.02),
        $friendly,
    ]);

    expect($decision)->toBe(['decision' => EvaluationDecision::Approved, 'rule' => $friendly]);
});

it('sends to review when an approve rule is not sure enough', function (): void {
    $decision = resolve(DecideModeration::class)->handle([
        ruleResult('approving', 0.97, 'Friendly'),
        ruleResult('approving', 0.59, 'On-topic'),
    ]);

    expect($decision)->toBe(['decision' => EvaluationDecision::Review, 'rule' => null]);
});

it('never approves without an approve rule', function (): void {
    $decision = resolve(DecideModeration::class)->handle([ruleResult('rejecting', 0.01)]);

    expect($decision)->toBe(['decision' => EvaluationDecision::Review, 'rule' => null]);
});

it('sends to review without any rules', function (): void {
    expect(resolve(DecideModeration::class)->handle([]))->toBe(['decision' => EvaluationDecision::Review, 'rule' => null]);
});

it('follows the strictness of each rule', function (ModerationStrictness $strictness, EvaluationDecision $decision): void {
    $result = resolve(DecideModeration::class)->handle([ruleResult('rejecting', 0.85, 'Hate', $strictness)]);

    expect($result['decision'])->toBe($decision);
})->with([
    'strict' => [ModerationStrictness::Strict, EvaluationDecision::Rejected],
    'balanced' => [ModerationStrictness::Balanced, EvaluationDecision::Review],
    'lenient' => [ModerationStrictness::Lenient, EvaluationDecision::Review],
]);
