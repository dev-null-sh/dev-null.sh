<?php

declare(strict_types=1);

namespace App\Support;

/** One-time, claimable milestone gifts; no purchase or cash-out. */
final class LevelRewards
{
    /** @var array<int, array{bytes: int, skin: ?string}> */
    public const array MILESTONES = [
        2 => ['bytes' => 75, 'skin' => null],
        3 => ['bytes' => 250, 'skin' => 'matrix'],
        4 => ['bytes' => 750, 'skin' => null],
        5 => ['bytes' => 1600, 'skin' => 'kernel-red'],
        6 => ['bytes' => 5000, 'skin' => null],
    ];
}
