<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Section;
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
 * @property-read string|null $commit_id
 * @property-read string|null $body
 * @property-read array{options: list<string>, duration: int}|null $poll
 * @property-read list<array{path: string, width: int, height: int, description: string|null, position: int}>|null $images
 * @property-read string|null $link_preview_url
 * @property-read Section|null $section
 * @property-read CarbonInterface $scheduled_at
 * @property-read CarbonInterface|null $submitted_at
 * @property-read CarbonInterface|null $canceled_at
 * @property-read User $user
 * @property-read Commit|null $commit
 */
#[Fillable(['user_id', 'body', 'poll', 'images', 'link_preview_url', 'section', 'scheduled_at'])]
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
            'section' => Section::class,
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

    /** @return BelongsTo<Commit, $this> */
    public function commit(): BelongsTo
    {
        return $this->belongsTo(Commit::class)->withTrashed();
    }
}
