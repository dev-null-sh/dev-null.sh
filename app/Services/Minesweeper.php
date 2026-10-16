<?php

declare(strict_types=1);

namespace App\Services;

use Random\Randomizer;

final readonly class Minesweeper
{
    /**
     * A week a column, like the commit activity graph: cells are numbered row by row, from 0 at the top left.
     */
    public const int ROWS = 7;

    public const int COLUMNS = 16;

    public const int MINES = 16;

    public const int CELLS = self::ROWS * self::COLUMNS;

    /**
     * The whole commit is the command, whatever the case. Anything else in the text is a different commit.
     */
    public function matches(string $body): bool
    {
        return mb_strtolower(mb_trim($body)) === '/minesweeper';
    }

    /**
     * Mines anywhere but on the first cell revealed and around it, so the first click always opens up the board.
     *
     * @return list<int>
     */
    public function plant(int $firstCell): array
    {
        $safe = [$firstCell, ...$this->neighbors($firstCell)];

        $cells = array_values(array_diff(range(0, self::CELLS - 1), $safe));

        $mines = array_slice(new Randomizer()->shuffleArray($cells), 0, self::MINES);

        sort($mines);

        return $mines;
    }

    /**
     * @return list<int>
     */
    public function neighbors(int $cell): array
    {
        $row = intdiv($cell, self::COLUMNS);
        $column = $cell % self::COLUMNS;
        $neighbors = [];

        foreach ([-1, 0, 1] as $rowOffset) {
            foreach ([-1, 0, 1] as $columnOffset) {
                $neighborRow = $row + $rowOffset;
                $neighborColumn = $column + $columnOffset;

                if (($rowOffset !== 0 || $columnOffset !== 0)
                    && $neighborRow >= 0 && $neighborRow < self::ROWS
                    && $neighborColumn >= 0 && $neighborColumn < self::COLUMNS) {
                    $neighbors[] = $neighborRow * self::COLUMNS + $neighborColumn;
                }
            }
        }

        return $neighbors;
    }

    /**
     * @param  list<int>  $mines
     */
    public function adjacentMines(int $cell, array $mines): int
    {
        return count(array_intersect($this->neighbors($cell), $mines));
    }

    /**
     * Reveals the cells, and every cell around a revealed cell with no mines next to it, like the classic game.
     *
     * @param  list<int>  $mines
     * @param  list<int>  $revealed
     * @param  list<int>  $cells
     * @return list<int>
     */
    public function reveal(array $mines, array $revealed, array $cells): array
    {
        $isRevealed = array_fill_keys($revealed, true);
        $queue = $cells;

        while ($queue !== []) {
            $cell = array_pop($queue);

            if (isset($isRevealed[$cell]) || in_array($cell, $mines, true)) {
                continue;
            }

            $isRevealed[$cell] = true;

            if ($this->adjacentMines($cell, $mines) === 0) {
                array_push($queue, ...$this->neighbors($cell));
            }
        }

        $revealed = array_keys($isRevealed);

        sort($revealed);

        return $revealed;
    }

    /**
     * @param  list<int>  $revealed
     */
    public function isCleared(array $revealed): bool
    {
        return count($revealed) === self::CELLS - self::MINES;
    }
}
