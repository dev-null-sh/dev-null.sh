<?php

declare(strict_types=1);

use App\Services\LinkPreviewBudget;

it('shares one monotonic deadline across the fetch', function (): void {
    $nanoseconds = 0;
    $clock = function () use (&$nanoseconds): int {
        return $nanoseconds;
    };
    $budget = new LinkPreviewBudget($clock);
    $deadline = $budget->start();
    $nanoseconds = 2_000_000_000;

    expect($deadline)->toBe(5.0)
        ->and($budget->remaining($deadline))->toBe(3.0);

    $nanoseconds = 6_000_000_000;

    expect($budget->remaining($deadline))->toBe(0.0);
});

it('does not pass a sub-millisecond timeout to the HTTP client', function (int $nanoseconds): void {
    $budget = new LinkPreviewBudget(fn (): int => $nanoseconds);

    expect($budget->remaining(5.0))->toBe(0.0);
})->with([4_999_999_999, 4_999_100_000]);
