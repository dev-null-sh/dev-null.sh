<?php

declare(strict_types=1);

namespace App\Models;

use App\Queries\CommitClaimBuilder;
use Carbon\CarbonInterface;
use Database\Factories\CommitClaimFactory;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read string $commit_id
 * @property-read string $user_id
 * @property-read CarbonInterface $expires_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Commit $commit
 * @property-read User $user
 */
#[UseEloquentBuilder(CommitClaimBuilder::class)]
#[Visible([
    'commit_id',
    'expires_at',
    'user',
])]
final class CommitClaim extends Model
{
    /** @use HasFactory<CommitClaimFactory> */
    use HasFactory;

    public const int MINUTES = 5;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'commit_id' => 'string',
            'user_id' => 'string',
            'expires_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
