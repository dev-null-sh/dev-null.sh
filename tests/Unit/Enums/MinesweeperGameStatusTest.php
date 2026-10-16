<?php

declare(strict_types=1);

use App\Enums\MinesweeperGameStatus;

it('has the statuses of a game of minesweeper', function (): void {
    expect(array_column(MinesweeperGameStatus::cases(), 'value', 'name'))->toBe([
        'Playing' => 'playing',
        'Won' => 'won',
        'Lost' => 'lost',
    ]);
});
