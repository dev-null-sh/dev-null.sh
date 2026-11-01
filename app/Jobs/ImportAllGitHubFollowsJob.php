<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CreateUserGitHubFollows;
use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * Imports the GitHub follows of every user not imported yet, a few users at a time, without notifying anyone: each job
 * queues the next one, after the users it read, so a run takes no worker for long, survives deploys and waits for the
 * GitHub rate limit to reset by itself. It is unique, so launching it twice doesn't run it twice.
 */
final class ImportAllGitHubFollowsJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public const int BatchSize = 25;

    /**
     * The requests left to the rest of the app, which shares the rate limit: above the floor where looking up mentioned
     * organizations pauses, so they carry on meanwhile.
     */
    public const int Reserve = 1500;

    public function __construct(public ?string $after = null)
    {
        //
    }

    public function handle(GitHubProvider $github, CreateUserGitHubFollows $createUserGitHubFollows): void
    {
        $rateLimit = $github->rateLimit();

        // A user takes up to the maximum of pages each way.
        if ($rateLimit !== null && $rateLimit['remaining'] < self::Reserve + self::BatchSize * 2 * CreateUserGitHubFollows::MaxPages) {
            // A minute past the reset, so GitHub counts from the new hour by then.
            $this->continueAt($rateLimit['resets_at']->addMinute());

            return;
        }

        $users = User::query()
            ->where(fn (Builder $query): Builder => $query->whereNull('github_follows_imported_at')->orWhereNull('github_organization_follows_imported_at'))
            ->when($this->after !== null, fn (Builder $query): Builder => $query->where('id', '>', $this->after))
            ->orderBy('id')
            ->limit(self::BatchSize)
            ->get();

        foreach ($users as $user) {
            try {
                $createUserGitHubFollows->handle($user, notify: false);
            } catch (RequestException $exception) {
                if (in_array($exception->response->status(), [403, 429], true)) {
                    $this->continueAt(now()->addMinutes(15));

                    return;
                }

                // An account renamed since its last sign-in isn't found under its handle: it is imported at its next one.
                report_if($exception->response->status() !== 404, $exception);
            } catch (ConnectionException $exception) {
                report($exception);
            }

            $this->after = $user->id;
        }

        if ($users->count() < self::BatchSize) {
            Log::info('Went through every user whose GitHub follows were not imported yet.');

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
