<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ModerationRuleAction;
use App\Enums\ModerationRuleMode;
use App\Enums\ModerationStrictness;
use App\Models\ModerationRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModerationRule>
 */
final class ModerationRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Spam and self-promotion',
            'matches' => 'Unsolicited advertising: selling products, services, courses, followers or stars.',
            'does_not_match' => 'Sharing your own article, project or open source work for discussion.',
            'action' => ModerationRuleAction::Reject,
            'strictness' => ModerationStrictness::Balanced,
            'mode' => ModerationRuleMode::Live,
            'rejection_message' => 'This looks like spam or self-promotion.',
            'position' => fn (): int => ModerationRule::query()->count(),
        ];
    }

    public function testing(): self
    {
        return $this->state(fn (array $attributes): array => [
            'mode' => ModerationRuleMode::Test,
        ]);
    }

    public function off(): self
    {
        return $this->state(fn (array $attributes): array => [
            'mode' => ModerationRuleMode::Off,
        ]);
    }

    public function flagging(): self
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Off-topic',
            'matches' => "Not about software, programming, technology, or a developer's working life.",
            'does_not_match' => null,
            'action' => ModerationRuleAction::Review,
            'rejection_message' => null,
        ]);
    }

    public function approving(): self
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Friendly dev content',
            'matches' => 'A harmless, on-topic post about software, technology or developer life.',
            'does_not_match' => 'Anything rude to a person, promotional, sexual, scammy, or unrelated to tech.',
            'action' => ModerationRuleAction::Approve,
            'rejection_message' => null,
        ]);
    }
}
