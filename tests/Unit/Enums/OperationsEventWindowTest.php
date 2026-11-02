<?php

declare(strict_types=1);

use App\Enums\OperationsEventWindow;
use Carbon\CarbonImmutable;

it('starts each window at the first of its buckets, the last one being the one now falls in', function (OperationsEventWindow $window, int $buckets, int $bucketSeconds, string $start): void {
    $now = CarbonImmutable::parse('2026-10-20 12:34:56');

    expect($window->buckets())->toBe($buckets)
        ->and($window->bucketSeconds())->toBe($bucketSeconds)
        ->and($window->start($now)->toDateTimeString())->toBe($start);
})->with([
    'an hour of 5 minutes' => [OperationsEventWindow::Hour, 12, 300, '2026-10-20 11:35:00'],
    'a day of hours' => [OperationsEventWindow::Day, 24, 3600, '2026-10-19 13:00:00'],
    'a week of 6 hours' => [OperationsEventWindow::Week, 28, 21600, '2026-10-13 18:00:00'],
    'a month of days' => [OperationsEventWindow::Month, 30, 86400, '2026-09-21 00:00:00'],
]);
