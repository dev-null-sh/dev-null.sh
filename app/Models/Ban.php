<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModerationReason;
use Carbon\CarbonInterface;
use Database\Factories\BanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $github_id
 * @property-read ModerationReason $reason
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User|null $user
 */
#[Fillable(['github_id', 'reason'])]
final class Ban extends Model
{
    /** @use HasFactory<BanFactory> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['reason' => ModerationReason::class];
    }

    /**
     * The banned user, unless they deleted their account.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'github_id', 'github_id');
    }
}
