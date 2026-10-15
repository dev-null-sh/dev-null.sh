<?php

declare(strict_types=1);

use App\Enums\ModerationRuleMode;

it('has the modes of a moderation rule', function (): void {
    expect(array_column(ModerationRuleMode::cases(), 'value', 'name'))->toBe([
        'Off' => 'off',
        'Test' => 'test',
        'Live' => 'live',
    ]);
});
