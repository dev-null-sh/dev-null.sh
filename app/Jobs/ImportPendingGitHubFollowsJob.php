<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CreatePendingFollows;
use App\Actions\CreateUserGitHubFollows;
use App\Models\User;
use App\Queries\GitHubRateLimitQuery;
use App\Services\GitHub;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * Remembers, for every user whose GitHub follows were imported, the accounts they follow on GitHub that aren't on
 * /dev/null yet, a few users at a time: each job queues the next one, after the users it read, so a run takes no
 * worker for long and survives deploys. It is unique, so launching it twice doesn't run it twice.
 */
final class ImportPendingGitHubFollowsJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    /**
     * Up to 250 requests a job, at 10 pages each.
     */
    public const int BatchSize = 25;

    /**
     * The requests left to the rest of the app, which shares the rate limit: sign-ins import their follows from it.
     */
    public const int Reserve = 2000;

    public function __construct(public ?string $after = null)
    {
        //
    }

    public function handle(GitHub $github, CreatePendingFollows $createPendingFollows, GitHubRateLimitQuery $rateLimit): void
    {
        $limit = $rateLimit->isConfigured() ? $rateLimit->get() : null;

        if ($limit !== null && $limit['remaining'] < self::Reserve) {
            // A minute past the reset, so the cached read of the rate limit is a fresh one by then.
            $this->continueAt(CarbonImmutable::parse($limit['resets_at'])->addMinute());

            return;
        }

        $users = User::query()
            ->whereNotNull('github_follows_imported_at')
            ->when($this->after !== null, fn (Builder $query): Builder => $query->where('id', '>', $this->after))
            ->orderBy('id')
            ->limit(self::BatchSize)
            ->get();

        foreach ($users as $user) {
            try {
                // The username is the GitHub login, as of their last sign-in, which saves looking it up by ID...
                $createPendingFollows->handle($user, $github->accountIds("users/{$user->username}/following", CreateUserGitHubFollows::MaxPages));
            } catch (RequestException $exception) {
                if (in_array($exception->response->status(), [403, 429], true)) {
                    $this->continueAt(now()->addMinutes(15));

                    return;
                }

                // ...and an account renamed or deleted since then is skipped.
                report_if($exception->response->status() !== 404, $exception);
            } catch (ConnectionException $exception) {
                report($exception);
            }

            $this->after = $user->id;
        }

        if ($users->count() < self::BatchSize) {
            Log::info('Remembered the pending GitHub follows of every user.');

            return;
        }

        $this->continueAt(now());
    }

    /**
     * Some queues can't hold a job back more than 15 minutes, so a longer wait reads the rate limit again by then.
     */
    private function continueAt(CarbonInterface $at): void
    {
        dispatch(new self($this->after))->delay($at->min(now()->addMinutes(15)));
    }
}
