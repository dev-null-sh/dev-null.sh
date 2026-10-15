<?php

declare(strict_types=1);

use App\Actions\AddStarterModerationRules;
use App\Enums\ModerationRuleAction;
use App\Enums\ModerationRuleMode;
use App\Enums\ModerationStrictness;
use App\Models\ModerationRule;

it('adds the starter rules in test mode, with off-topic off', function (): void {
    resolve(AddStarterModerationRules::class)->handle();

    $rules = ModerationRule::query()->ordered()->get();

    expect($rules->pluck('name')->all())->toBe([
        'Spam and self-promotion',
        'Scams, phishing and malware',
        'Harassment and personal attacks',
        'Hate and slurs',
        'Sexual content',
        'Gaming the moderator',
        'Off-topic',
        'Friendly dev content',
    ])
        ->and($rules->pluck('position')->all())->toBe([0, 1, 2, 3, 4, 5, 6, 7])
        ->and($rules->where('mode', ModerationRuleMode::Test))->toHaveCount(7)
        ->and($rules->firstWhere('name', 'Off-topic'))
        ->mode->toBe(ModerationRuleMode::Off)
        ->action->toBe(ModerationRuleAction::Review)
        ->rejection_message->toBeNull()
        ->and($rules->firstWhere('name', 'Scams, phishing and malware'))
        ->strictness->toBe(ModerationStrictness::Strict)
        ->rejection_message->toBe('This looks like a scam or an unsafe link or command.')
        ->and($rules->firstWhere('name', 'Friendly dev content'))
        ->action->toBe(ModerationRuleAction::Approve);
});

it('skips starter rules that already exist and adds the rest after them', function (): void {
    ModerationRule::factory()->create(['name' => 'Spam and self-promotion', 'position' => 0]);
    ModerationRule::factory()->create(['name' => 'My own rule', 'position' => 4]);

    resolve(AddStarterModerationRules::class)->handle();

    expect(ModerationRule::query()->count())->toBe(9)
        ->and(ModerationRule::query()->where('name', 'Spam and self-promotion')->count())->toBe(1)
        ->and(ModerationRule::query()->where('name', 'Scams, phishing and malware')->value('position'))->toBe(5);
});
