<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\CommitRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $commit_id
 * @property-read string $body
 * @property-read array<string, string>|null $mentions
 * @property-read CarbonInterface $created_at
 * @property-read Commit $commit
 */
#[Visible([
    'body',
    'created_at',
])]
final class CommitRevision extends Model
{
    /** @use HasFactory<CommitRevisionFactory> */
    use HasFactory;

    use HasUuids;

    public const ?string UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'commit_id' => 'string',
            'body' => 'string',
            'created_at' => 'datetime',
            'mentions' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Commit, $this>
     */
    public function commit(): BelongsTo
    {
        return $this->belongsTo(Commit::class);
    }
}
