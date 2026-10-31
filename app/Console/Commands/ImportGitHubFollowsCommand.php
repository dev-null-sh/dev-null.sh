<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CreateUserGitHubFollows;
use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

#[Signature('follows:import')]
#[Description('Follow, for every user not imported yet, who and which organizations they follow and who follows them on GitHub, without notifying anyone')]
final class ImportGitHubFollowsCommand extends Command
{
    /**
     * Below this many requests left, the command stops, leaving the rest to the app until the rate limit resets: above
     * the floor where looking up mentioned organizations pauses, so they carry on meanwhile.
     */
    private const int RateLimitFloor = 1500;

    public function handle(CreateUserGitHubFollows $createUserGitHubFollows, GitHubProvider $github): int
    {
        $imported = 0;
        $failed = 0;

        foreach ($this->users()->lazyById() as $user) {
            $rateLimit = $github->rateLimit();

            // A user takes one request for their login, and up to the maximum of pages each way.
            if ($rateLimit !== null && $rateLimit['remaining'] < self::RateLimitFloor + 1 + 2 * CreateUserGitHubFollows::MaxPages) {
                $this->components->warn("Stopped after {$imported} users, to keep ".self::RateLimitFloor." GitHub requests for the rest of the app: {$rateLimit['remaining']} left.");
                $this->components->info("{$this->users()->count()} users left. Run the command again after {$rateLimit['resets_at']->utc()->toDateTimeString()} UTC ({$rateLimit['resets_at']->diffForHumans()}), when the GitHub rate limit resets.");

                return self::FAILURE;
            }

            try {
                $createUserGitHubFollows->handle($user, notify: false);

                $imported++;
            } catch (RequestException $exception) {
                if (in_array($exception->response->status(), [403, 429], true)) {
                    $this->components->error("GitHub rate limit reached after {$imported} users. Run the command again later.");

                    return self::FAILURE;
                }

                $this->failed($user, $exception);

                $failed++;
            } catch (ConnectionException $exception) {
                $this->failed($user, $exception);

                $failed++;
            }
        }

        $this->components->info("Imported the GitHub follows of {$imported} users.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return Builder<User>
     */
    private function users(): Builder
    {
        return User::query()->where(fn (Builder $query): Builder => $query->whereNull('github_follows_imported_at')->orWhereNull('github_organization_follows_imported_at'));
    }

    private function failed(User $user, RequestException|ConnectionException $exception): void
    {
        report($exception);

        $this->components->warn("Could not import the GitHub follows of {$user->mention()}.");
    }
}
