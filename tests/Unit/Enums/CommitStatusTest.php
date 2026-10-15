<?php

declare(strict_types=1);

use App\Enums\CommitStatus;

it('has the moderation statuses of a commit', function (): void {
    expect(array_column(CommitStatus::cases(), 'value', 'name'))->toBe([
        'Pending' => 'pending',
        'Approved' => 'approved',
        'Rejected' => 'rejected',
    ]);
});
