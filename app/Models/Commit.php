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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read string $id
 * @property-read string $user_id
 * @property-read string $title
 * @property-read string|null $description
 * @property-read CommitStatus $status
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User $user
 * @property-read Collection<int, Topic> $topics
 * @property-read Collection<int, Topic> $promotedTopics
 */
#[UseEloquentBuilder(CommitBuilder::class)]
final class Commit extends Model
{
    /** @use HasFactory<CommitFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'user_id' => 'string',
            'title' => 'string',
            'description' => 'string',
            'status' => CommitStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Topic, $this>
     */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class);
    }

    /**
     * The topics a commit card shows: only those enough commits share to have been promoted.
     *
     * @return BelongsToMany<Topic, $this>
     */
    public function promotedTopics(): BelongsToMany
    {
        return $this->topics()
            ->has('commits', '>=', Topic::MIN_COMMITS)
            ->select('topics.id', 'topics.name');
    }
}
