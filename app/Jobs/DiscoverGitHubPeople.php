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
use Throwable;
use UnexpectedValueException;

/**
 * @phpstan-import-type DiscoveryState from GitHubPeopleQuery
 */
#[Tries(1)]
#[Timeout(45)]
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
        $nextPage = Cache::lock("github-people-scan:{$this->user->id}:{$this->id}", 60)->get(function () use ($following, $people): ?int {
            $state = $people->snapshot($this->user);

            if ($state === null || ! $this->isCurrent($state)) {
                return null;
            }

            try {
                $result = $following->getPage($this->user, $this->page);
            } catch (ConnectionException|RequestException|UnexpectedValueException $exception) {
                $this->storeFailure($state, $exception);

                return null;
            }

            $current = $people->snapshot($this->user);

            if ($current === null || ! $this->isCurrent($current)) {
                return null;
            }

            $ids = array_values(array_unique([...$state['github_ids'], ...$result['github_ids']]));

            Cache::put('github-people:'.$this->user->id, [
                ...$state,
                'status' => $result['has_more'] ? 'loading' : 'ready',
                'github_ids' => $ids,
                'username' => $result['username'],
                'page' => $this->page,
                'scanned' => count($ids),
                'fetched_at' => $result['has_more'] ? null : now()->toIso8601String(),
            ], now()->addHour());

            return $result['has_more'] ? $this->page + 1 : null;
        });

        if (is_int($nextPage)) {
            try {
                dispatch(new self($this->user, $this->id, $nextPage));
            } catch (Throwable $exception) {
                $this->failed($exception);

                throw $exception;
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        /** @var DiscoveryState|null $state */
        $state = Cache::get('github-people:'.$this->user->id);

        if ($state !== null && $state['id'] === $this->id && $state['status'] === 'loading'
            && in_array($state['page'], [$this->page - 1, $this->page], true)) {
            $this->storeFailure($state, $exception);
        }
    }

    /**
     * @param  DiscoveryState  $state
     */
    private function isCurrent(array $state): bool
    {
        return $state['id'] === $this->id && $state['status'] === 'loading' && $this->page === $state['page'] + 1;
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
