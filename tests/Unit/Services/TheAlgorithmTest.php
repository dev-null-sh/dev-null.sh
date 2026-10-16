<?php

declare(strict_types=1);

use App\Services\TheAlgorithm;
use Illuminate\Support\Facades\Date;

it('ranks a commit with twice the engagement like one committed twelve hours later', function (): void {
    $theAlgorithm = new TheAlgorithm();
    $createdAt = Date::parse('2026-10-16 12:00:00');

    expect($theAlgorithm->score($createdAt, 1, 0))
        ->toEqualWithDelta($theAlgorithm->score($createdAt->addHours(12), 0, 0), 1e-9);
});

it('counts each developer replying as three stars', function (): void {
    $theAlgorithm = new TheAlgorithm();
    $createdAt = Date::parse('2026-10-16 12:00:00');

    expect($theAlgorithm->score($createdAt, 0, 1))
        ->toBe($theAlgorithm->score($createdAt, 3, 0));
});
