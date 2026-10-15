<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * @phpstan-type DiscoveryState array{id: string, status: 'loading'|'ready'|'unavailable'|'rate_limited'|'renamed', github_ids: list<string>, username: string|null, page: int, scanned: int, fetched_at: string|null, retry_at: string|null, pages_requested?: int, restart_count?: int, truncated?: bool, previous_id?: string}
 */
final readonly class GitHubPeopleQuery
{
    /**
     * @return array{status: 'idle'|'loading'|'ready'|'unavailable'|'rate_limited'|'renamed', users: Collection<int, User>, scanned: int, fetched_at: string|null, retry_at: string|null, truncated: bool}
     */
    public function get(User $user): array
    {
        $state = $this->snapshot($user);
        $users = new Collection();

        if (($state['status'] ?? null) === 'ready') {
            foreach (array_chunk($state['github_ids'], 500) as $ids) {
                $users = $users->concat(User::query()
                    ->whereIn('github_id', $ids)
                    ->whereKeyNot($user->id)
                    ->withExists(['followers as is_followed' => fn (Builder $query): Builder => $query->whereKey($user->id)])
                    ->get());
            }
        }

        return [
            'status' => $state['status'] ?? 'idle',
            'users' => $users->sortBy([['name', 'asc'], ['id', 'asc']])->values(),
            'scanned' => $state['scanned'] ?? 0,
            'fetched_at' => $state['fetched_at'] ?? null,
            'retry_at' => $state['retry_at'] ?? null,
            'truncated' => $state['truncated'] ?? false,
        ];
    }

    /**
     * @return DiscoveryState|null
     */
    public function snapshot(User $user): ?array
    {
        /** @var DiscoveryState|null $state */
        $state = Cache::get('github-people:'.$user->id);

        return $state;
    }
}
