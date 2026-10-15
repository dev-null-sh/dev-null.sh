<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\DiscoverGitHubPeople;
use App\Models\User;
use App\Queries\GitHubPeopleQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Throwable;

final readonly class CreateGitHubDiscovery
{
    public function __construct(private GitHubPeopleQuery $people)
    {
        //
    }

    public function handle(User $user): void
    {
        Cache::lock('github-people-start:'.$user->id, 10)->get(function () use ($user): void {
            $state = $this->people->snapshot($user);

            if ($state !== null && (in_array($state['status'], ['loading', 'ready'], true)
                || ($state['retry_at'] !== null && Date::parse($state['retry_at'])->isFuture()))) {
                return;
            }

            $id = Str::uuid7()->toString();

            Cache::put('github-people:'.$user->id, [
                'id' => $id,
                'status' => 'loading',
                'github_ids' => [],
                'username' => null,
                'page' => 0,
                'scanned' => 0,
                'fetched_at' => null,
                'retry_at' => null,
                'pages_requested' => 0,
                'restart_count' => 0,
                'truncated' => false,
            ], now()->addHour());

            try {
                dispatch(new DiscoverGitHubPeople($user, $id));
            } catch (Throwable $throwable) {
                $current = $this->people->snapshot($user);

                if ($current !== null && $current['id'] === $id && $current['status'] === 'loading') {
                    Cache::put('github-people:'.$user->id, [
                        ...($state ?? $current),
                        'status' => 'unavailable',
                        'retry_at' => now()->addMinute()->toIso8601String(),
                    ], now()->addHour());
                }

                throw $throwable;
            }
        });
    }
}
