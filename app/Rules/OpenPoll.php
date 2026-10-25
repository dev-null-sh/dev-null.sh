<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\Poll;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Votes are final, and authors see their results instead of voting.
 */
final readonly class OpenPoll implements ValidationRule
{
    public function __construct(private Commit $commit, private User $voter, private bool $allowExistingVote = false)
    {
        //
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $poll = $this->commit->poll;

        if (! $poll instanceof Poll) {
            return;
        }

        if ($this->commit->status !== CommitStatus::Approved || $poll->opened_at === null) {
            $fail('This poll is not open for votes.');
        } elseif ($this->commit->user_id === $this->voter->id) {
            $fail("You can't vote on your own poll.");
        } elseif ($poll->isClosed()) {
            $fail('This poll has closed.');
        } elseif (! $this->allowExistingVote && $poll->votes()->where('user_id', $this->voter->id)->exists()) {
            $fail('You have already voted on this poll.');
        }
    }
}
