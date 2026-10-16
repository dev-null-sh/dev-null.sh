<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MinesweeperGameStatus;
use App\Services\Minesweeper;
use Carbon\CarbonInterface;
use Database\Factories\MinesweeperGameFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $commit_id
 * @property-read string $user_id
 * @property-read MinesweeperGameStatus $status
 * @property-read list<int> $mines
 * @property-read list<int> $revealed
 * @property-read int|null $exploded_cell
 * @property-read int|null $duration_ms
 * @property-read CarbonInterface|null $finished_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Commit $commit
 * @property-read User $user
 */
#[DateFormat('Y-m-d H:i:s.v')]
final class MinesweeperGame extends Model
{
    /** @use HasFactory<MinesweeperGameFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'commit_id' => 'string',
            'user_id' => 'string',
            'status' => MinesweeperGameStatus::class,
            'mines' => 'array',
            'revealed' => 'array',
            'exploded_cell' => 'integer',
            'duration_ms' => 'integer',
            'finished_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The board as the player sees it: the number of mines around each revealed cell, null for the ones still hidden.
     * The mines only show once the game is over, so they can never be read off a game in play.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $minesweeper = resolve(Minesweeper::class);
        $cells = array_fill(0, Minesweeper::CELLS, null);

        foreach ($this->revealed as $cell) {
            $cells[$cell] = $minesweeper->adjacentMines($cell, $this->mines);
        }

        return [
            'id' => $this->id,
            'status' => $this->status,
            'cells' => $cells,
            'mines' => $this->isFinished() ? $this->mines : null,
            'exploded_cell' => $this->exploded_cell,
            'duration_ms' => $this->duration_ms,
            'created_at' => $this->created_at->toJSON(),
            'finished_at' => $this->finished_at?->toJSON(),
        ];
    }

    public function isFinished(): bool
    {
        return $this->status !== MinesweeperGameStatus::Playing;
    }

    /**
     * @return BelongsTo<Commit, $this>
     */
    public function commit(): BelongsTo
    {
        return $this->belongsTo(Commit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
