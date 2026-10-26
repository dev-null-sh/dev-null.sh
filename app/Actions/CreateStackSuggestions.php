<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Queries\DependencyContributorsQuery;
use Illuminate\Support\Facades\Cache;

/**
 * Remembers who is behind a user's stack so they are suggested to follow, rather than followed for them: a follow
 * lets someone message you, and that should be your choice.
 */
final readonly class CreateStackSuggestions
{
    /**
     * Long enough to cover someone's first days, after which the usual suggestions take over.
     */
    private const int Days = 7;

    public function __construct(private DependencyContributorsQuery $dependencyContributors)
    {
        //
    }

    public static function key(User $user): string
    {
        return "stack-suggestions:{$user->id}";
    }

    public function handle(User $user): void
    {
        $ids = User::query()
            ->whereIn('github_id', $this->dependencyContributors->get($user->handle))
            ->whereKeyNot($user->id)
            ->pluck('id')
            ->all();

        Cache::put(self::key($user), $ids, now()->addDays(self::Days));
    }
}
