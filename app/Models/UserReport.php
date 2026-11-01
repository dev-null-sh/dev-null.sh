<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\UserReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A private report about a person, from their profile or a private chat. It never holds anything from a conversation.
 *
 * @property-read string $id
 * @property-read string $user_id
 * @property-read string $reporter_id
 * @property-read string $reason
 * @property-read string|null $details
 * @property-read CarbonInterface|null $dismissed_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User $user
 */
#[Visible(['id', 'reason', 'details', 'created_at', 'priority', 'user'])]
#[Fillable(['user_id', 'reporter_id', 'reason', 'details'])]
final class UserReport extends Model
{
    /** @use HasFactory<UserReportFactory> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['details' => 'encrypted', 'dismissed_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
