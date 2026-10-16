<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommitStatus;
use Carbon\CarbonInterface;
use Database\Factories\CommitDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read string $commit_id
 * @property-read string|null $user_id
 * @property-read CommitStatus $status
 * @property-read string|null $reason
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Commit $commit
 * @property-read User|null $user
 */
#[Visible([
    'id',
    'status',
    'reason',
    'created_at',
    'commit',
    'user',
])]
final class CommitDecision extends Model
{
    /** @use HasFactory<CommitDecisionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'commit_id' => 'string',
            'user_id' => 'string',
            'status' => CommitStatus::class,
            'reason' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Commit, $this>
     */
    public function commit(): BelongsTo
    {
        return $this->belongsTo(Commit::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
