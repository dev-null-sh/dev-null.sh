<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateMinesweeperGame;
use App\Actions\UpdateMinesweeperGame;
use App\Http\Requests\CreateMinesweeperGameRequest;
use App\Http\Requests\ListMinesweeperGamesRequest;
use App\Http\Requests\UpdateMinesweeperGameRequest;
use App\Models\Commit;
use App\Models\MinesweeperGame;
use App\Models\User;
use App\Queries\MinesweeperScoresQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class MinesweeperGameController
{
    public function index(ListMinesweeperGamesRequest $request, #[CurrentUser] User $user, Commit $commit, MinesweeperScoresQuery $scores): JsonResponse
    {
        return response()->json($scores->get($commit, $user));
    }

    public function store(CreateMinesweeperGameRequest $request, #[CurrentUser] User $user, Commit $commit, CreateMinesweeperGame $action, MinesweeperScoresQuery $scores): JsonResponse
    {
        $action->handle($user, $commit, $request->cell());

        return response()->json($scores->get($commit, $user));
    }

    public function update(UpdateMinesweeperGameRequest $request, #[CurrentUser] User $user, Commit $commit, MinesweeperGame $minesweeperGame, UpdateMinesweeperGame $action, MinesweeperScoresQuery $scores): JsonResponse
    {
        $action->handle($minesweeperGame, $request->cells());

        return response()->json($scores->get($commit, $user));
    }
}
