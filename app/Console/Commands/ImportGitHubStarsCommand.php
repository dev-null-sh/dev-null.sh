<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CreateUserGitHubStars;
use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

#[Signature('stars:import')]
#[Description('Remember, for every user not imported yet, whose repositories they starred on GitHub, for Who to follow')]
final class ImportGitHubStarsCommand extends Command
{
    /**
     * Below this many requests left, the command stops, leaving the rest to the app until the rate limit resets, as
     * follows:import does.
     */
    private const int RateLimitFloor = 1500;

    public function handle(CreateUserGitHubStars $createUserGitHubStars, GitHubProvider $github): int
    {
        $imported = 0;
        $failed = 0;

        foreach ($this->users()->lazyById() as $user) {
            $rateLimit = $github->rateLimit();

            // A user takes one request for their login, and up to the maximum of pages of stars.
            if ($rateLimit !== null && $rateLimit['remaining'] < self::RateLimitFloor + 1 + CreateUserGitHubStars::MaxPages) {
                $this->components->warn("Stopped after {$imported} users, to keep ".self::RateLimitFloor." GitHub requests for the rest of the app: {$rateLimit['remaining']} left.");
                $this->components->info("{$this->users()->count()} users left. Run the command again after {$rateLimit['resets_at']->utc()->toDateTimeString()} UTC ({$rateLimit['resets_at']->diffForHumans()}), when the GitHub rate limit resets.");

                return self::FAILURE;
            }

            try {
                $createUserGitHubStars->handle($user);

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

        $this->components->info("Imported the GitHub stars of {$imported} users.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return Builder<User>
     */
    private function users(): Builder
    {
        return User::query()->whereNull('github_stars_imported_at');
    }

    private function failed(User $user, RequestException|ConnectionException $exception): void
    {
        report($exception);

        $this->components->warn("Could not import the GitHub stars of {$user->mention()}.");
    }
}
