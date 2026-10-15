<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommitStatus;
use App\Queries\CommitBuilder;
use Carbon\CarbonInterface;
use Database\Factories\CommitFactory;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read string $id
 * @property-read string $user_id
 * @property-read string|null $parent_id
 * @property-read string $body
 * @property-read CommitStatus $status
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read CarbonInterface|null $deleted_at
 * @property-read User $user
 * @property-read Commit|null $parent
 * @property-read Collection<int, Commit> $replies
 * @property-read int $replies_count
 */
#[UseEloquentBuilder(CommitBuilder::class)]
final class Commit extends Model
{
    /** @use HasFactory<CommitFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'user_id' => 'string',
            'parent_id' => 'string',
            'body' => 'string',
            'status' => CommitStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * A deleted commit only ever leaves a tombstone behind: never its author or body.
     *
     * @return array<mixed>
     */
    public function toArray(): array
    {
        if ($this->trashed()) {
            return array_intersect_key($this->attributesToArray(), array_flip(['id', 'parent_id', 'deleted_at', 'replies_count']));
        }

        return parent::toArray();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Commit, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Commit, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Loads what anyone may see around the commit: its author, its approved replies, and its parent only when approved.
     */
    public function loadPublicPreview(): self
    {
        return $this->loadCount(['replies' => fn (CommitBuilder $query): CommitBuilder => $query->approved()])
            ->load(['user', 'parent' => fn (BelongsTo $query): BelongsTo => $query->where('status', CommitStatus::Approved)->with('user')]);
    }

    /**
     * Changes whenever anything drawn on the commit's link preview image may have changed.
     */
    public function openGraphImageVersion(): string
    {
        return hash('xxh3', implode(':', [
            $this->updated_at->getTimestamp(),
            $this->replies_count,
            $this->user->updated_at->getTimestamp(),
            $this->parent?->user->username,
        ]));
    }
}
