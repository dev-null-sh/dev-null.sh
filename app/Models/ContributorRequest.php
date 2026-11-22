<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContributorHelp;
use App\Enums\ContributorRequestStatus;
use Carbon\CarbonInterface;
use Database\Factories\ContributorRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $user_id
 * @property-read string $commit_id
 * @property-read string $project_url
 * @property-read ContributorHelp $kind
 * @property-read ContributorRequestStatus $status
 * @property-read CarbonInterface|null $filled_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Commit|null $commit
 * @property-read User $user
 */
final class ContributorRequest extends Model
{
    /** @use HasFactory<ContributorRequestFactory> */
    use HasFactory;

    use HasUuids;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'open', 'filled_at' => null];

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'id' => 'string', 'user_id' => 'string', 'commit_id' => 'string', 'project_url' => 'string',
            'kind' => ContributorHelp::class, 'status' => ContributorRequestStatus::class,
            'filled_at' => 'datetime', 'created_at' => 'datetime', 'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Commit, $this> */
    public function commit(): BelongsTo
    {
        return $this->belongsTo(Commit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
