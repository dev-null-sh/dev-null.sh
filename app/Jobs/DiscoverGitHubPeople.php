<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Queries\GitHubPeopleQuery;
use App\Services\GitHubFollowing;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;
use UnexpectedValueException;

/**
 * @phpstan-import-type DiscoveryState from GitHubPeopleQuery
 */
#[Tries(1)]
#[Timeout(60)]
#[UniqueFor(3600)]
#[DeleteWhenMissingModels]
final class DiscoverGitHubPeople implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly User $user, public readonly string $id, public readonly int $page = 1)
    {
        //
    }

    public function uniqueId(): string
    {
        return "{$this->user->id}:{$this->id}:{$this->page}";
    }

    public function handle(GitHubFollowing $following, GitHubPeopleQuery $people): void
    {
        $nextPage = Cache::lock("github-people-scan:{$this->user->id}:{$this->id}", 90)->get(function () use ($following, $people): ?self {
            $state = $people->snapshot($this->user);

            if ($state === null || ! $this->isCurrent($state)) {
                return null;
            }

            try {
                $state['username'] ??= $following->resolveUsername($this->user);
                $afterOwnerLookup = $people->snapshot($this->user);

                if (! $this->isCurrent($afterOwnerLookup)) {
                    return null;
                }

                $requested = $state['pages_requested'] ?? $state['page'];

                if ($requested >= 5) {
                    return $this->complete($following, $people, $state, true);
                }

                $state['pages_requested'] = $requested + 1;
                Cache::put('github-people:'.$this->user->id, $state, now()->addHour());

                try {
                    $result = $following->getPage($state['username'], $this->page);
                } catch (RequestException $exception) {
                    $status = $exception->response->status();

                    throw_if($status !== 404 && ($status < 300 || $status >= 400), $exception);

                    $username = $following->resolveUsername($this->user);
                    $afterRenameLookup = $people->snapshot($this->user);

                    if (! $this->isCurrent($afterRenameLookup)) {
                        return null;
                    }

                    throw_if(strcasecmp($username, $state['username']) === 0, $exception);

                    return $this->restart($state, $username);
                }

                $afterFollowingPage = $people->snapshot($this->user);

                if (! $this->isCurrent($afterFollowingPage)) {
                    return null;
                }

                $ids = array_values(array_unique([...$state['github_ids'], ...$result['github_ids']]));
                $state = [
                    ...$state,
                    'github_ids' => $ids,
                    'page' => $this->page,
                    'scanned' => count($ids),
                ];

                if (! $result['has_more'] || $state['pages_requested'] >= 5) {
                    return $this->complete($following, $people, $state, $result['has_more']);
                }

                Cache::put('github-people:'.$this->user->id, $state, now()->addHour());

                return new self($this->user, $this->id, $this->page + 1);
            } catch (ConnectionException|RequestException|UnexpectedValueException $exception) {
                $current = $people->snapshot($this->user);

                if ($current !== null && $this->isCurrent($current)) {
                    $this->storeFailure($current, $exception);
                }

                return null;
            }
        });

        if ($nextPage instanceof self) {
            $this->dispatchPage($nextPage);
        }
    }

    public function failed(?Throwable $exception): void
    {
        /** @var DiscoveryState|null $state */
        $state = Cache::get('github-people:'.$this->user->id);

        if ($state !== null && $this->isCurrent($state)) {
            $this->storeFailure($state, $exception);
        } elseif ($state !== null && $state['id'] === $this->id && $state['status'] === 'loading' && $state['page'] === $this->page) {
            $this->dispatchPage(new self($this->user, $this->id, $this->page + 1), 90);
        } elseif ($state !== null && $state['id'] !== $this->id && ($state['previous_id'] ?? null) === $this->id && $state['status'] === 'loading' && $state['page'] === 0) {
            $this->dispatchPage(new self($this->user, $state['id']), 90);
        }
    }

    /**
     * @param  DiscoveryState  $state
     */
    private function complete(GitHubFollowing $following, GitHubPeopleQuery $people, array $state, bool $hasMore): ?self
    {
        $username = $following->resolveUsername($this->user);
        $current = $people->snapshot($this->user);

        if (! $this->isCurrent($current)) {
            return null;
        }

        if (strcasecmp($username, (string) $state['username']) !== 0) {
            return $this->restart($state, $username);
        }

        Cache::put('github-people:'.$this->user->id, [
            ...$state,
            'status' => 'ready',
            'truncated' => $hasMore,
            'fetched_at' => now()->toIso8601String(),
        ], now()->addHour());

        return null;
    }

    /**
     * @param  DiscoveryState  $state
     */
    private function restart(array $state, string $username): ?self
    {
        $canRestart = ($state['restart_count'] ?? 0) === 0 && ($state['pages_requested'] ?? $state['page']) < 5;
        $state = [
            ...$state,
            'id' => $canRestart ? Str::uuid7()->toString() : $state['id'],
            'status' => $canRestart ? 'loading' : 'renamed',
            'username' => $username,
            'github_ids' => [],
            'page' => 0,
            'scanned' => 0,
            'fetched_at' => null,
            'retry_at' => $canRestart ? null : now()->addMinute()->toIso8601String(),
            'restart_count' => ($state['restart_count'] ?? 0) + 1,
            'truncated' => false,
            'previous_id' => $this->id,
        ];
        Cache::put('github-people:'.$this->user->id, $state, now()->addHour());

        return $canRestart ? new self($this->user, $state['id']) : null;
    }

    private function dispatchPage(self $job, int $delay = 0): void
    {
        try {
            dispatch($job)->delay($delay);
        } catch (Throwable $throwable) {
            /** @var DiscoveryState|null $state */
            $state = Cache::get('github-people:'.$this->user->id);

            if ($state !== null && $state['id'] === $job->id && $state['page'] === $job->page - 1) {
                $this->storeFailure($state, $throwable);
            }

            throw $throwable;
        }
    }

    /**
     * @param  DiscoveryState|null  $state
     */
    private function isCurrent(?array $state): bool
    {
        return $state !== null && $state['id'] === $this->id && $state['status'] === 'loading' && $this->page === $state['page'] + 1;
    }

    /**
     * @param  DiscoveryState  $state
     */
    private function storeFailure(array $state, ?Throwable $exception): void
    {
        /** @var DiscoveryState|null $current */
        $current = Cache::get('github-people:'.$this->user->id);

        if ($current === null || $current['id'] !== $state['id'] || $current['status'] !== 'loading' || $current['page'] !== $state['page']) {
            return;
        }

        $response = $exception instanceof RequestException ? $exception->response : null;
        $limited = $response !== null && ($response->tooManyRequests()
            || ($response->forbidden() && ($response->header('X-RateLimit-Remaining') === '0' || $response->header('Retry-After') !== '')));
        $retryAt = now()->addMinute();

        if ($limited) {
            $reset = $response->header('X-RateLimit-Reset');
            $retryAfter = $response->header('Retry-After');
            $timestamp = max($retryAt->getTimestamp(), ctype_digit($reset) ? (int) $reset : 0,
                ctype_digit($retryAfter) ? now()->getTimestamp() + (int) $retryAfter : 0);
            $retryAt = $retryAt->setTimestamp($timestamp);
        }

        Cache::put('github-people:'.$this->user->id, [
            ...$state,
            'status' => $limited ? 'rate_limited' : 'unavailable',
            'retry_at' => $retryAt->toIso8601String(),
        ], now()->addHour());
    }
}
