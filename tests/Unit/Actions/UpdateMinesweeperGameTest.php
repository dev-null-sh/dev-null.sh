<?php

declare(strict_types=1);

use App\Actions\UpdateMinesweeperGame;
use App\Enums\MinesweeperGameStatus;
use App\Models\MinesweeperGame;

it('reveals safe cells and keeps playing', function (): void {
    $game = MinesweeperGame::factory()->create(['mines' => [0, ...range(97, 111)]]);

    $game = resolve(UpdateMinesweeperGame::class)->handle($game, [1]);

    expect($game->status)->toBe(MinesweeperGameStatus::Playing)
        ->and($game->revealed)->toBe([1])
        ->and($game->finished_at)->toBeNull()
        ->and($game->duration_ms)->toBeNull();
});

it('loses on a mine, timed from the start of the game', function (): void {
    $game = MinesweeperGame::factory()->create(['revealed' => [0]]);

    $this->travel(12_345)->milliseconds();

    $game = resolve(UpdateMinesweeperGame::class)->handle($game, [1, 100]);

    expect($game->status)->toBe(MinesweeperGameStatus::Lost)
        ->and($game->exploded_cell)->toBe(100)
        ->and($game->revealed)->toBe([0])
        ->and($game->finished_at?->format('Y-m-d H:i:s.v'))->toBe(now()->format('Y-m-d H:i:s.v'))
        ->and($game->duration_ms)->toBe(12_345);
});

it('wins once the last safe cell is revealed', function (): void {
    $game = MinesweeperGame::factory()->create();

    $this->travel(41)->seconds();

    $game = resolve(UpdateMinesweeperGame::class)->handle($game, [0]);

    expect($game->status)->toBe(MinesweeperGameStatus::Won)
        ->and($game->revealed)->toBe(range(0, 95))
        ->and($game->exploded_cell)->toBeNull()
        ->and($game->duration_ms)->toBe(41_000);
});

it('leaves a finished game as it ended', function (): void {
    $game = MinesweeperGame::factory()->lost()->create();

    $game = resolve(UpdateMinesweeperGame::class)->handle($game, [0]);

    expect($game->status)->toBe(MinesweeperGameStatus::Lost)
        ->and($game->revealed)->toBe([])
        ->and($game->duration_ms)->toBe(10_000);
});
