<?php

declare(strict_types=1);

use App\Dto\ThreadEngagement;
use App\Services\TheAlgorithm;
use Illuminate\Support\Facades\Date;

it('ranks a commit with twice the engagement like one committed six hours later', function (): void {
    $theAlgorithm = new TheAlgorithm();
    $createdAt = Date::parse('2026-10-16 12:00:00');

    expect($theAlgorithm->score($createdAt, new ThreadEngagement(stargazers: 2, quoters: 0, repliers: 0, repliersAnsweredByAuthor: 0)))
        ->toEqualWithDelta($theAlgorithm->score($createdAt->addHours(6), ThreadEngagement::none()), 1e-9);
});

it('weighs each developer quoting as two developers starring', function (): void {
    $theAlgorithm = new TheAlgorithm();
    $createdAt = Date::parse('2026-10-16 12:00:00');

    expect($theAlgorithm->score($createdAt, new ThreadEngagement(stargazers: 0, quoters: 1, repliers: 0, repliersAnsweredByAuthor: 0)))
        ->toBe($theAlgorithm->score($createdAt, new ThreadEngagement(stargazers: 2, quoters: 0, repliers: 0, repliersAnsweredByAuthor: 0)));
});

it('weighs each developer replying as 27 developers starring', function (): void {
    $theAlgorithm = new TheAlgorithm();
    $createdAt = Date::parse('2026-10-16 12:00:00');

    expect($theAlgorithm->score($createdAt, new ThreadEngagement(stargazers: 0, quoters: 0, repliers: 1, repliersAnsweredByAuthor: 0)))
        ->toBe($theAlgorithm->score($createdAt, new ThreadEngagement(stargazers: 27, quoters: 0, repliers: 0, repliersAnsweredByAuthor: 0)));
});

it('weighs each developer replying with an answer from the author as 177 developers starring', function (): void {
    $theAlgorithm = new TheAlgorithm();
    $createdAt = Date::parse('2026-10-16 12:00:00');

    expect($theAlgorithm->score($createdAt, new ThreadEngagement(stargazers: 0, quoters: 0, repliers: 1, repliersAnsweredByAuthor: 1)))
        ->toBe($theAlgorithm->score($createdAt, new ThreadEngagement(stargazers: 177, quoters: 0, repliers: 0, repliersAnsweredByAuthor: 0)));
});
