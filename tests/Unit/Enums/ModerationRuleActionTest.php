<?php

declare(strict_types=1);

use App\Enums\ModerationRuleAction;

it('has the actions a moderation rule may take', function (): void {
    expect(array_column(ModerationRuleAction::cases(), 'value', 'name'))->toBe([
        'Reject' => 'reject',
        'Review' => 'review',
        'Approve' => 'approve',
    ]);
});
