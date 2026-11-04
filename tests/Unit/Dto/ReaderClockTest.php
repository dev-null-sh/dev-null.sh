<?php

declare(strict_types=1);

use App\Dto\ReaderClock;

it('gives the current IANA name of a time zone, which PHP knows even without the legacy aliases', function (?string $timeZone, ?string $ianaTimeZone): void {
    $clock = new ReaderClock($timeZone, null);

    expect($clock->ianaTimeZone())->toBe($ianaTimeZone);
})->with([
    'unknown' => [null, null],
    'already current' => ['Europe/Paris', 'Europe/Paris'],
    'a former name browsers still report' => ['Asia/Calcutta', 'Asia/Kolkata'],
    'a legacy regional alias' => ['US/Eastern', 'America/New_York'],
    'UTC' => ['UTC', 'Etc/UTC'],
]);
