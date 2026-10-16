<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Commit;
use App\Models\CommitClaim;
use App\Models\User;
use App\Queries\CommitBuilder;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class Unclaimed implements ValidationRule
{
    public function __construct(private User $user)
    {
        //
    }

    /**
     * Fails while another admin is reviewing the commit, so two admins don't moderate the same one.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $claim = CommitClaim::query()
            ->active()
            ->whereIn('commit_id', Commit::query()
                ->where(fn (CommitBuilder $query): CommitBuilder => $query
                    ->whereKey($value)
                    ->orWhereIn('series_id', Commit::query()->whereKey($value)->whereNotNull('series_id')->select('series_id')))
                ->select('id'))
            ->whereNot('user_id', $this->user->id)
            ->with('user')
            ->first();

        if ($claim instanceof CommitClaim) {
            $fail(__('@:username is reviewing this commit.', ['username' => $claim->user->username]));
        }
    }
}
