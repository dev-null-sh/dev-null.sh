<?php

declare(strict_types=1);

use App\Actions\CreateMinesweeperGame;
use App\Enums\MinesweeperGameStatus;
use App\Models\Commit;
use App\Models\MinesweeperGame;
use App\Models\User;
use App\Services\Minesweeper;

it('starts a game with its first cell revealed and safe', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create(['body' => '/minesweeper']);

    $game = resolve(CreateMinesweeperGame::class)->handle($user, $commit, 56);

    expect($game->status)->toBe(MinesweeperGameStatus::Playing)
        ->and($game->user_id)->toBe($user->id)
        ->and($game->commit_id)->toBe($commit->id)
        ->and($game->mines)->toHaveCount(Minesweeper::MINES)
        ->and($game->mines)->not->toContain(56)
        ->and($game->revealed)->toContain(56)
        ->and($game->revealed)->toContain(...resolve(Minesweeper::class)->neighbors(56))
        ->and($game->finished_at)->toBeNull();
});

it('walks away from the game in play, but keeps finished ones', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create(['body' => '/minesweeper']);
    $playing = MinesweeperGame::factory()->for($user)->for($commit)->create();
    $won = MinesweeperGame::factory()->for($user)->for($commit)->won()->create();
    $elsewhere = MinesweeperGame::factory()->for($user)->create();
    $someoneElse = MinesweeperGame::factory()->for($commit)->create();

    $game = resolve(CreateMinesweeperGame::class)->handle($user, $commit, 0);

    expect(MinesweeperGame::query()->find($playing->id))->toBeNull()
        ->and(MinesweeperGame::query()->pluck('id')->all())->toEqualCanonicalizing([$won->id, $elsewhere->id, $someoneElse->id, $game->id]);
});
