<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Remembers whose repositories a user starred on GitHub, once, so Who to follow can suggest those people. Owners not
 * on /dev/null yet are kept too, for when they join. It never follows anyone.
 */
final readonly class CreateUserGitHubStars
{
    /**
     * The 300 repositories starred last, so someone who stars everything can't use up the rate limit on their own.
     */
    public const int MaxPages = 3;

    public function __construct(private GitHubProvider $github)
    {
        //
    }

    public function handle(User $user): void
    {
        if ($user->github_stars_imported_at !== null) {
            return;
        }

        $login = $this->github->login($user->github_id);
        $ownerIds = $login === null ? [] : $this->github->starredOwnerIds($login, self::MaxPages);

        $rows = collect($ownerIds)
            ->reject(fn (string $id): bool => $id === $user->github_id)
            ->countBy()
            ->map(fn (int $count, int|string $id): array => [
                'user_id' => $user->id,
                'github_id' => (string) $id,
                'repositories_count' => $count,
                'created_at' => now(),
            ])
            ->values()
            ->all();

        DB::transaction(function () use ($user, $rows): void {
            DB::table('starred_github_owners')->insertOrIgnore($rows);

            $user->update(['github_stars_imported_at' => now()]);
        });
    }
}
