<?php

declare(strict_types=1);

use App\Enums\RuleOutcome;

it('has the outcomes of a moderation rule', function (): void {
    expect(array_column(RuleOutcome::cases(), 'value', 'name'))->toBe([
        'Reject' => 'reject',
        'Review' => 'review',
        'Approve' => 'approve',
        'None' => 'none',
    ]);
});
