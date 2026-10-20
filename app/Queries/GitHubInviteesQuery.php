<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use App\Services\GitHub;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;

final readonly class GitHubInviteesQuery
{
    /**
     * Up to 1,000 accounts, like the follows brought over at sign up, so a popular account can't use up the rate limit.
     */
    private const int MaxPages = 10;

    public function __construct(private GitHub $github)
    {
        //
    }

    /**
     * The people the user follows on GitHub who are not on /dev/null yet, in GitHub's order. The GitHub list is cached
     * for six hours, while who already joined is read every time, so someone who signs up drops off the list at once.
     * Null when GitHub didn't answer, which is not cached, so the next visit tries again.
     *
     * @return list<array{id: string, login: string, avatar_url: string}>|null
     */
    public function get(User $user): ?array
    {
        try {
            /** @var list<array{id: string, login: string, avatar_url: string}> $following */
            $following = Cache::remember("github-invitees:{$user->id}", 21600, function () use ($user): array {
                $login = $this->github->login($user->github_id);

                return $login === null ? [] : $this->github->accounts("users/{$login}/following", self::MaxPages);
            });
        } catch (HttpClientException) {
            return null;
        }

        $joined = User::query()
            ->whereIn('github_id', array_column($following, 'id'))
            ->pluck('github_id')
            ->flip();

        return array_values(array_filter($following, fn (array $account): bool => ! $joined->has($account['id'])));
    }
}
