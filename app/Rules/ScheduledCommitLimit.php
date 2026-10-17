<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\ScheduledCommit;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ScheduledCommitLimit implements ValidationRule
{
    public function __construct(private User $user)
    {
        //
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $outstanding = ScheduledCommit::query()
            ->where('user_id', $this->user->id)
            ->whereNull('submitted_at')
            ->whereNull('canceled_at')
            ->count();

        if ($outstanding >= 10) {
            $fail('You can have up to 10 scheduled commits. Cancel one before scheduling another.');
        }
    }
}
