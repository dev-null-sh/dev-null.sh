<?php

declare(strict_types=1);

use App\Enums\MinesweeperGameStatus;
use App\Models\Commit;
use App\Models\MinesweeperGame;
use App\Models\User;
use App\Services\Minesweeper;

it('shows the revealed cells with their counts, and hides the mines while in play', function (): void {
    $game = MinesweeperGame::factory()->create(['revealed' => [0, 80]])->refresh();

    $array = $game->toArray();

    expect(array_keys($array))->toBe(['id', 'status', 'cells', 'mines', 'exploded_cell', 'duration_ms', 'created_at', 'finished_at'])
        ->and($array['status'])->toBe(MinesweeperGameStatus::Playing)
        ->and($array['cells'])->toHaveCount(Minesweeper::CELLS)
        ->and($array['cells'][0])->toBe(0)
        ->and($array['cells'][80])->toBe(2)
        ->and($array['cells'][1])->toBeNull()
        ->and($array['mines'])->toBeNull()
        ->and($array['finished_at'])->toBeNull();
});

it('shows the mines once the game is over', function (): void {
    $game = MinesweeperGame::factory()->lost()->create()->refresh();

    expect($game->isFinished())->toBeTrue()
        ->and($game->toArray()['mines'])->toBe(range(96, 111))
        ->and($game->toArray()['exploded_cell'])->toBe(96)
        ->and($game->toArray()['finished_at'])->toBe(now()->startOfMillisecond()->toJSON());
});

it('belongs to a commit and a player', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create(['body' => '/minesweeper']);

    $game = MinesweeperGame::factory()->for($user)->for($commit)->create();

    expect($game->user->is($user))->toBeTrue()
        ->and($game->commit->is($commit))->toBeTrue()
        ->and($commit->minesweeperGames->sole()->is($game))->toBeTrue();
});

it('goes with its commit', function (): void {
    $game = MinesweeperGame::factory()->create();

    $game->commit->forceDelete();

    expect(MinesweeperGame::query()->find($game->id))->toBeNull();
});
