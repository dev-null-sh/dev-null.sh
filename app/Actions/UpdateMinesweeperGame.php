<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MinesweeperGameStatus;
use App\Models\MinesweeperGame;
use App\Services\Minesweeper;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final readonly class UpdateMinesweeperGame
{
    public function __construct(private Minesweeper $minesweeper)
    {
        //
    }

    /**
     * Reveals the cells: a mine among them loses the game, and revealing the last safe cell wins it. The time is the
     * server's, from the first cell revealed to the last, so a game can't be timed by its player.
     *
     * @param  list<int>  $cells
     */
    public function handle(MinesweeperGame $game, array $cells): MinesweeperGame
    {
        return DB::transaction(function () use ($game, $cells): MinesweeperGame {
            $game = MinesweeperGame::query()->lockForUpdate()->findOrFail($game->id);

            if ($game->isFinished()) {
                return $game;
            }

            $exploded = array_values(array_intersect($cells, $game->mines));
            $revealed = $exploded === [] ? $this->minesweeper->reveal($game->mines, $game->revealed, $cells) : $game->revealed;

            $status = match (true) {
                $exploded !== [] => MinesweeperGameStatus::Lost,
                $this->minesweeper->isCleared($revealed) => MinesweeperGameStatus::Won,
                default => MinesweeperGameStatus::Playing,
            };

            $finishedAt = $status === MinesweeperGameStatus::Playing ? null : now();

            $game->forceFill([
                'status' => $status,
                'revealed' => $revealed,
                'exploded_cell' => $exploded[0] ?? null,
                'finished_at' => $finishedAt,
                'duration_ms' => $finishedAt instanceof CarbonInterface ? (int) $game->created_at->diffInMilliseconds($finishedAt) : null,
            ])->save();

            return $game;
        });
    }
}
