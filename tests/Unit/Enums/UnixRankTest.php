<?php

declare(strict_types=1);

use App\Enums\UnixRank;

it('has the unix ranks', function (): void {
    expect(array_column(UnixRank::cases(), 'value', 'name'))->toBe([
        'User' => 'user',
        'Daemon' => 'daemon',
        'Operator' => 'operator',
        'Maintainer' => 'maintainer',
        'Wheel' => 'wheel',
        'Root' => 'root',
    ]);
});

it('resolves ranks by xp thresholds', function (int $xp, UnixRank $expectedRank): void {
    expect(UnixRank::forXp($xp))->toBe($expectedRank);
})->with([
    [0, UnixRank::User],
    [49, UnixRank::User],
    [50, UnixRank::Daemon],
    [149, UnixRank::Daemon],
    [150, UnixRank::Operator],
    [399, UnixRank::Operator],
    [400, UnixRank::Maintainer],
    [999, UnixRank::Maintainer],
    [1000, UnixRank::Wheel],
    [2499, UnixRank::Wheel],
    [2500, UnixRank::Root],
    [5000, UnixRank::Root],
]);

it('provides expected properties for every unix rank', function (UnixRank $rank): void {
    expect($rank->level())->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(6);
    expect($rank->minXp())->toBeGreaterThanOrEqual(0);
    expect($rank->description())->toBeString()->not->toBeEmpty();
    expect($rank->auraColor())->toBeString()->not->toBeEmpty();
})->with(UnixRank::cases());

it('determines the next rank in progression', function (UnixRank $rank, ?UnixRank $nextRank): void {
    expect($rank->next())->toBe($nextRank);
})->with([
    [UnixRank::User, UnixRank::Daemon],
    [UnixRank::Daemon, UnixRank::Operator],
    [UnixRank::Operator, UnixRank::Maintainer],
    [UnixRank::Maintainer, UnixRank::Wheel],
    [UnixRank::Wheel, UnixRank::Root],
    [UnixRank::Root, null],
]);
