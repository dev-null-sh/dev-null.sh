<?php

declare(strict_types=1);

use App\Enums\EvaluationDecision;

it('has the decisions of a commit evaluation', function (): void {
    expect(array_column(EvaluationDecision::cases(), 'value', 'name'))->toBe([
        'Approved' => 'approved',
        'Rejected' => 'rejected',
        'Review' => 'review',
        'Failed' => 'failed',
    ]);
});
