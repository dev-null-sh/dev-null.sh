<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModerationReason;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Database\Factories\UserTimeoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $github_id
 * @property-read ModerationReason $reason
 * @property-read CarbonInterface $ends_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User|null $user
 */
#[Fillable(['github_id', 'reason', 'ends_at'])]
final class UserTimeout extends Model
{
    /** @use HasFactory<UserTimeoutFactory> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'reason' => ModerationReason::class,
            'ends_at' => 'datetime',
        ];
    }

    /**
     * The user in timeout, unless they deleted their account.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'github_id', 'github_id');
    }

    /**
     * What the user is told when they try to post, with how much longer the timeout lasts, rounded up.
     */
    public function message(): string
    {
        return __("You're in timeout for :remaining (:reason). You can read, but you can't post until it's over.", [
            'remaining' => $this->remaining(),
            'reason' => $this->reason->label(),
        ]);
    }

    /**
     * What the web shows in the dialog that stops the user from posting.
     *
     * @return array{reason: string, remaining: string, ends_at: string}
     */
    public function details(): array
    {
        return [
            'reason' => $this->reason->label(),
            'remaining' => $this->remaining(),
            'ends_at' => $this->ends_at->toIso8601ZuluString(),
        ];
    }

    /**
     * Rounded up to the minute, or to the hour past a day, so it never says less is left than there is.
     */
    private function remaining(): string
    {
        $minutes = (int) ceil(now()->diffInSeconds($this->ends_at) / 60);
        $minutes = $minutes >= 1440 ? (int) ceil($minutes / 60) * 60 : $minutes;

        return CarbonInterval::minutes($minutes)->cascade()->forHumans(['parts' => 2, 'skip' => ['month', 'week']]);
    }
}
