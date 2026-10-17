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
            $fail("{$reference} is not a commit hash or link.");
        } elseif ($commits->count() > 1) {
            $fail("{$reference} matches several commits: use the link of the commit instead.");
        } elseif ($commits->isEmpty()) {
            $fail("There is no commit {$reference} to reply to.");
        }
    }
}
