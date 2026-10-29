<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MergeConflictSide;
use Carbon\CarbonInterface;
use Database\Factories\MergeConflictVoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read string $merge_conflict_id
 * @property-read string $user_id
 * @property-read MergeConflictSide $side
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read MergeConflict $mergeConflict
 * @property-read User $user
 */
final class MergeConflictVote extends Model
{
    /** @use HasFactory<MergeConflictVoteFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'merge_conflict_id' => 'string',
            'user_id' => 'string',
            'side' => MergeConflictSide::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MergeConflict, $this>
     */
    public function mergeConflict(): BelongsTo
    {
        return $this->belongsTo(MergeConflict::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
