<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MinesweeperGameStatus;
use App\Models\Commit;
use App\Models\MinesweeperGame;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MinesweeperGame>
 */
final class MinesweeperGameFactory extends Factory
{
    /**
     * The mines fill the bottom row, so every other cell is safe and the top left opens up the rest of the board.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commit_id' => Commit::factory()->state(['body' => '/minesweeper']),
            'user_id' => User::factory(),
            'status' => MinesweeperGameStatus::Playing,
            'mines' => range(96, 111),
            'revealed' => [],
        ];
    }

    public function won(int $durationMs = 30_000): self
    {
        return $this->state(fn (): array => [
            'status' => MinesweeperGameStatus::Won,
            'revealed' => range(0, 95),
            'duration_ms' => $durationMs,
            'finished_at' => now(),
        ]);
    }

    public function lost(int $durationMs = 10_000): self
    {
        return $this->state(fn (): array => [
            'status' => MinesweeperGameStatus::Lost,
            'exploded_cell' => 96,
            'duration_ms' => $durationMs,
            'finished_at' => now(),
        ]);
    }
}
