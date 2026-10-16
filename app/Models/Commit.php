<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommitStatus;
use App\Queries\CommitBuilder;
use App\Services\CodeSpans;
use Carbon\CarbonInterface;
use Database\Factories\CommitFactory;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read string $id
 * @property-read string $user_id
 * @property-read string|null $parent_id
 * @property-read string $body
 * @property-read CommitStatus $status
 * @property-read string|null $link_preview_id
 * @property-read string|null $series_id
 * @property-read int|null $series_position
 * @property-read int|null $series_size
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read CarbonInterface|null $deleted_at
 * @property-read int $stars_count
 * @property-read bool $starred
 * @property-read User $user
 * @property-read Collection<int, User> $stars
 * @property-read Commit|null $parent
 * @property-read Collection<int, Commit> $replies
 * @property-read int $replies_count
 * @property-read bool $is_saved
 * @property-read LinkPreview|null $linkPreview
 * @property-read Collection<int, CommitImage> $images
 * @property-read Collection<int, Commit> $seriesParts
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
            'link_preview_id' => 'string',
            'series_id' => 'string',
            'series_position' => 'integer',
            'series_size' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * A deleted commit only ever leaves a tombstone behind: never its author or body, though a deleted part of a series
     * keeps its place in it. Otherwise, the body also comes split into prose, inline code and highlighted code blocks, for rendering.
     *
     * @return array<mixed>
     */
    public function toArray(): array
    {
        if ($this->trashed()) {
            $keys = ['id', 'parent_id', 'deleted_at', 'replies_count', ...($this->series_id === null ? [] : ['series_position', 'series_size'])];

            return array_intersect_key($this->attributesToArray(), array_flip($keys));
        }

        return [...parent::toArray(), 'body_segments' => resolve(CodeSpans::class)->highlight($this->body)];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function stars(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'stars')->withTimestamps();
    }

    /**
     * Approved commits are visible to everyone. Authors can also see their own.
     */
    public function isVisibleTo(?User $user): bool
    {
        return $this->status === CommitStatus::Approved || $this->user_id === $user?->id;
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
     * Every part of the series the commit belongs to, the commit included.
     *
     * @return HasMany<Commit, $this>
     */
    public function seriesParts(): HasMany
    {
        return $this->hasMany(self::class, 'series_id', 'series_id');
    }

    /**
     * @return HasMany<Bookmark, $this>
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    /**
     * @return BelongsTo<LinkPreview, $this>
     */
    public function linkPreview(): BelongsTo
    {
        return $this->belongsTo(LinkPreview::class);
    }

    /**
     * @return HasMany<CommitImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(CommitImage::class)->orderBy('position');
    }

    /**
     * Images follow the commit's visibility, and moderators see them to review the commit.
     */
    public function imagesAreVisibleTo(?User $user): bool
    {
        if ($this->trashed()) {
            return false;
        }

        if ($this->status === CommitStatus::Approved) {
            return true;
        }

        return $user instanceof User && ($this->user_id === $user->id || $user->isAdmin());
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
