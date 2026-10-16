<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\MinesweeperGameStatus;
use App\Models\Commit;
use App\Models\MinesweeperGame;
use App\Models\User;

final readonly class MinesweeperScoresQuery
{
    /**
     * The player's latest game on the commit, the fastest win on it by anyone the player can see, the player's own
     * fastest win on any commit, and how often the commit was played to the end.
     *
     * @return array{game: MinesweeperGame|null, best: array{duration_ms: int, user: array{username: string, name: string}}|null, personal_best: int|null, played: int}
     */
    public function get(Commit $commit, User $user): array
    {
        $best = MinesweeperGame::query()
            ->whereBelongsTo($commit)
            ->where('status', MinesweeperGameStatus::Won)
            ->whereNotIn('user_id', $user->idsAcrossBlocks())
            ->with('user:id,username,name')
            ->orderBy('duration_ms')
            ->orderBy('finished_at')
            ->first();

        $personalBest = MinesweeperGame::query()
            ->whereBelongsTo($user)
            ->where('status', MinesweeperGameStatus::Won)
            ->min('duration_ms');

        return [
            'game' => MinesweeperGame::query()->whereBelongsTo($commit)->whereBelongsTo($user)->latest()->first(),
            'best' => $best instanceof MinesweeperGame && $best->duration_ms !== null ? [
                'duration_ms' => $best->duration_ms,
                'user' => ['username' => $best->user->username, 'name' => $best->user->name],
            ] : null,
            'personal_best' => is_numeric($personalBest) ? (int) $personalBest : null,
            'played' => MinesweeperGame::query()->whereBelongsTo($commit)->where('status', '!=', MinesweeperGameStatus::Playing)->count(),
        ];
    }
}
