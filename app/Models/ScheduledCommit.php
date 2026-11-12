<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ScheduledCommitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $user_id
 * @property-read string|null $author_id
 * @property-read string|null $commit_id
 * @property-read string|null $body
 * @property-read array{options: list<string>, duration: int}|null $poll
 * @property-read list<array{path: string, width: int, height: int, description: string|null, position: int}>|null $images
 * @property-read string|null $link_preview_url
 * @property-read CarbonInterface $scheduled_at
 * @property-read CarbonInterface|null $submitted_at
 * @property-read CarbonInterface|null $canceled_at
 * @property-read User $user
 * @property-read Profile|null $author
 * @property-read Commit|null $commit
 */
#[Fillable(['user_id', 'author_id', 'body', 'poll', 'images', 'link_preview_url', 'scheduled_at'])]
final class ScheduledCommit extends Model
{
    /** @use HasFactory<ScheduledCommitFactory> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'poll' => 'array',
            'images' => 'array',
            'scheduled_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'canceled_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The organization it goes out as, or null when it goes out as its user.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Profile::class)->with(['user', 'organization']);
    }

    /** @return BelongsTo<Commit, $this> */
    public function commit(): BelongsTo
    {
        return $this->belongsTo(Commit::class)->withTrashed();
    }
}
