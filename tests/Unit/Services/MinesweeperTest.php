<?php

declare(strict_types=1);

use App\Services\Minesweeper;

it('recognizes a minesweeper commit', function (string $body): void {
    expect(resolve(Minesweeper::class)->matches($body))->toBeTrue();
})->with([
    'plain' => '/minesweeper',
    'caps' => '/Minesweeper',
    'padded' => "  /minesweeper\n",
]);

it('ignores anything that is not only the command', function (string $body): void {
    expect(resolve(Minesweeper::class)->matches($body))->toBeFalse();
})->with([
    'no slash' => 'minesweeper',
    'extra words' => '/minesweeper please',
    'quoted' => '`/minesweeper`',
    'empty' => '',
]);

it('plants the mines away from the first cell and its neighbors', function (int $firstCell): void {
    $minesweeper = resolve(Minesweeper::class);

    $mines = $minesweeper->plant($firstCell);

    expect($mines)->toHaveCount(Minesweeper::MINES)
        ->and(array_unique($mines))->toHaveCount(Minesweeper::MINES)
        ->and(array_intersect($mines, [$firstCell, ...$minesweeper->neighbors($firstCell)]))->toBe([])
        ->and(min($mines))->toBeGreaterThanOrEqual(0)
        ->and(max($mines))->toBeLessThan(Minesweeper::CELLS);

    $sorted = $mines;
    sort($sorted);

    expect($mines)->toBe($sorted);
})->with([
    'corner' => 0,
    'middle' => 56,
    'last' => Minesweeper::CELLS - 1,
]);

it('finds the neighbors of a cell within the board', function (int $cell, array $neighbors): void {
    expect(resolve(Minesweeper::class)->neighbors($cell))->toBe($neighbors);
})->with([
    'top left' => [0, [1, 16, 17]],
    'top right' => [15, [14, 30, 31]],
    'middle' => [17, [0, 1, 2, 16, 18, 32, 33, 34]],
    'bottom right' => [111, [94, 95, 110]],
]);

it('counts the mines around a cell', function (): void {
    $minesweeper = resolve(Minesweeper::class);

    expect($minesweeper->adjacentMines(17, [0, 2, 34, 50]))->toBe(3)
        ->and($minesweeper->adjacentMines(17, [17]))->toBe(0);
});

it('reveals a numbered cell on its own', function (): void {
    expect(resolve(Minesweeper::class)->reveal([0], [], [1]))->toBe([1]);
});

it('opens up the board around cells with no mines next to them, up to the numbered cells', function (): void {
    $minesweeper = resolve(Minesweeper::class);

    $revealed = $minesweeper->reveal(range(96, 111), [], [0]);

    expect($revealed)->toBe(range(0, 95))
        ->and($minesweeper->isCleared($revealed))->toBeTrue();
});

it('never reveals a mine, nor a cell already revealed twice', function (): void {
    expect(resolve(Minesweeper::class)->reveal([0], [5], [0, 5, 1]))->toBe([1, 5]);
});

it('is cleared only once every safe cell is revealed', function (): void {
    $minesweeper = resolve(Minesweeper::class);

    expect($minesweeper->isCleared(range(0, 94)))->toBeFalse()
        ->and($minesweeper->isCleared(range(0, 95)))->toBeTrue();
});
