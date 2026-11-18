<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\ScheduledCommit;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ScheduledCommitLimit implements ValidationRule
{
    public const int MAX = 10;

    public const string MESSAGE = 'You can have up to 10 scheduled commits. Cancel one before scheduling another.';

    public function __construct(private User $user)
    {
        //
    }

    /**
     * The user's scheduled commits still waiting for their time.
     */
    public static function outstanding(User $user): int
    {
        return ScheduledCommit::query()
            ->where('user_id', $user->id)
            ->whereNull('submitted_at')
            ->whereNull('canceled_at')
            ->count();
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (self::outstanding($this->user) >= self::MAX) {
            $fail(self::MESSAGE);
        }
    }
}
