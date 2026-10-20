<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitBuilder;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class VisibleCommit implements ValidationRule
{
    public function __construct(private User $user, private ?string $message = null, private bool $allowsSections = true)
    {
        //
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Commit::query()->visibleTo($this->user)->unless($this->allowsSections, fn (CommitBuilder $query): CommitBuilder => $query->withoutSections())->whereKey($value)->exists()) {
            $fail($this->message ?? 'That commit is no longer available to reply to.');
        }
    }
}
