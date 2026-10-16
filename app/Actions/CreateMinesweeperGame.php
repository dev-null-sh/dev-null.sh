<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MinesweeperGameStatus;
use App\Models\Commit;
use App\Models\MinesweeperGame;
use App\Models\User;
use App\Services\Minesweeper;
use Illuminate\Support\Facades\DB;

final readonly class CreateMinesweeperGame
{
    public function __construct(
        private UpdateMinesweeperGame $updateMinesweeperGame,
        private Minesweeper $minesweeper,
    ) {
        //
    }

    /**
     * A game starts with its first cell revealed, so the mines are planted around it. Starting over walks away from
     * the game in play, which is never recorded.
     */
    public function handle(User $user, Commit $commit, int $firstCell): MinesweeperGame
    {
        return DB::transaction(function () use ($user, $commit, $firstCell): MinesweeperGame {
            MinesweeperGame::query()
                ->whereBelongsTo($user)
                ->whereBelongsTo($commit)
                ->where('status', MinesweeperGameStatus::Playing)
                ->delete();

            $game = MinesweeperGame::query()->create([
                'commit_id' => $commit->id,
                'user_id' => $user->id,
                'status' => MinesweeperGameStatus::Playing,
                'mines' => $this->minesweeper->plant($firstCell),
                'revealed' => [],
            ]);

            return $this->updateMinesweeperGame->handle($game, [$firstCell]);
        });
    }
}
