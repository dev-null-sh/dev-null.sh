<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PollDuration;
use Carbon\CarbonInterface;
use Database\Factories\PollFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $id
 * @property-read string $commit_id
 * @property-read PollDuration $duration
 * @property-read CarbonInterface|null $opened_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read bool|null $is_own
 * @property-read Commit|null $commit
 * @property-read Collection<int, PollOption> $options
 * @property-read Collection<int, PollVote> $votes
 */
final class Poll extends Model
{
    /** @use HasFactory<PollFactory> */
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
            'duration' => PollDuration::class,
            'opened_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Counts are only revealed to the author, to voters, and to everyone once the poll has closed, so they can't sway a vote. Voters are never revealed.
     *
     * @return array{id: string, closes_at: string|null, is_closed: bool, has_voted: bool, total_votes: int|null, options: list<array{id: string, label: string, votes_count: int|null, voted: bool}>}
     */
    public function toArray(): array
    {
        $options = $this->options->all();
        $hasVoted = $this->options->contains(fn (PollOption $option): bool => $option->voted === true);
        $showsResults = $hasVoted || $this->is_own === true || $this->isClosed();
        $closesAt = $this->closesAt();

        return [
            'id' => $this->id,
            'closes_at' => $closesAt instanceof CarbonInterface ? $this->serializeDate($closesAt) : null,
            'is_closed' => $this->isClosed(),
            'has_voted' => $hasVoted,
            'total_votes' => $showsResults ? array_sum(array_map(fn (PollOption $option): int => $option->votes_count ?? 0, $options)) : null,
            'options' => array_values(array_map(fn (PollOption $option): array => [
                'id' => $option->id,
                'label' => $option->label,
                'votes_count' => $showsResults ? $option->votes_count ?? 0 : null,
                'voted' => $option->voted === true,
            ], $options)),
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
     * @return HasMany<PollOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('position');
    }

    /**
     * @return HasMany<PollVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    /**
     * The clock starts once the commit is approved, so time spent in moderation doesn't count.
     */
    public function closesAt(): ?CarbonInterface
    {
        return $this->opened_at?->copy()->addMinutes($this->duration->value);
    }

    public function isClosed(): bool
    {
        $closesAt = $this->closesAt();

        return $closesAt instanceof CarbonInterface && now()->greaterThanOrEqualTo($closesAt);
    }
}
