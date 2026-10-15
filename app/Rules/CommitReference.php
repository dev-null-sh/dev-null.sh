<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\User;
use App\Queries\CommitByReferenceQuery;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class CommitReference implements ValidationRule
{
    public function __construct(private User $user)
    {
        //
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $reference = is_string($value) ? $value : '';

        $commits = resolve(CommitByReferenceQuery::class)->get($this->user, $reference);

        if ($commits === null) {
            $fail(__(':reference is not a commit hash or link.', ['reference' => $reference]));
        } elseif ($commits->count() > 1) {
            $fail(__(':reference matches several commits: use the link of the commit instead.', ['reference' => $reference]));
        } elseif ($commits->isEmpty()) {
            $fail(__('There is no commit :reference to reply to.', ['reference' => $reference]));
        }
    }
}
