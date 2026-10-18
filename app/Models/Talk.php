<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\TalkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $user_id
 * @property-read string|null $commit_id
 * @property-read string $title
 * @property-read string $description
 * @property-read CarbonInterface $presented_on
 * @property-read string|null $slides_url
 * @property-read string|null $recording_url
 * @property-read string|null $event_name
 * @property-read bool $is_unlisted
 * @property-read CarbonInterface|null $published_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User $user
 * @property-read Commit|null $commit
 */
#[Fillable(['user_id', 'title', 'description', 'presented_on', 'slides_url', 'recording_url', 'event_name', 'is_unlisted'])]
final class Talk extends Model
{
    /** @use HasFactory<TalkFactory> */
    use HasFactory;

    use HasUuids;

    public const int MIN_TITLE_LENGTH = 3;

    public const int MAX_TITLE_LENGTH = 200;

    public const int MAX_DESCRIPTION_LENGTH = 4000;

    public const int MAX_EVENT_NAME_LENGTH = 200;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'presented_on' => 'immutable_date',
            'is_unlisted' => 'boolean',
            'published_at' => 'immutable_datetime',
        ];
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->commit_id !== null;
    }

    public function isDraft(): bool
    {
        return ! $this->isPublished();
    }

    public function isDiscoverable(): bool
    {
        return $this->isPublished() && ! $this->is_unlisted;
    }

    public function isVisibleTo(?User $user): bool
    {
        if ($this->isDraft()) {
            return $user?->id === $this->user_id;
        }

        $commit = $this->commit;

        if (! $commit instanceof Commit) {
            return $user?->id === $this->user_id;
        }

        return $commit->isVisibleTo($user);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Commit, $this> */
    public function commit(): BelongsTo
    {
        return $this->belongsTo(Commit::class)->withTrashed();
    }
}
