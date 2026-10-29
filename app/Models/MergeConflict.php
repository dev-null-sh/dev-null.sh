<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MergeConflictSide;
use Carbon\CarbonInterface;
use Database\Factories\MergeConflictFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Two replies to the same commit that disagree, spotted by Tom, for readers to pick a side between.
 *
 * @property-read string $id
 * @property-read string $commit_id
 * @property-read string $ours_id
 * @property-read string $theirs_id
 * @property-read CarbonInterface $closes_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Commit $commit
 * @property-read Commit $ours
 * @property-read Commit $theirs
 * @property-read Collection<int, MergeConflictVote> $votes
 */
final class MergeConflict extends Model
{
    /** @use HasFactory<MergeConflictFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * How long readers have to pick a side.
     */
    public const int VotingHours = 24;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'commit_id' => 'string',
            'ours_id' => 'string',
            'theirs_id' => 'string',
            'closes_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Commit, $this>
     */
    public function commit(): BelongsTo
    {
        return $this->belongsTo(Commit::class);
    }

    /**
     * @return BelongsTo<Commit, $this>
     */
    public function ours(): BelongsTo
    {
        return $this->belongsTo(Commit::class, 'ours_id');
    }

    /**
     * @return BelongsTo<Commit, $this>
     */
    public function theirs(): BelongsTo
    {
        return $this->belongsTo(Commit::class, 'theirs_id');
    }

    /**
     * @return HasMany<MergeConflictVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(MergeConflictVote::class);
    }

    public function isClosed(): bool
    {
        return now()->greaterThanOrEqualTo($this->closes_at);
    }

    /**
     * The two authors are a side each, so they don't get a vote.
     */
    public function canBeVotedOnBy(User $user): bool
    {
        return ! $this->isClosed() && ! in_array($user->id, [$this->ours->user_id, $this->theirs->user_id], true);
    }

    public function sideOf(User $user): ?MergeConflictSide
    {
        return $this->votes()->whereBelongsTo($user)->first()?->side;
    }
}
