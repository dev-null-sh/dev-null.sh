<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CreateUserGitHubFollows;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

#[Signature('follows:import')]
#[Description('Follow, for every user not imported yet, who they follow and who follows them on GitHub, without notifying anyone')]
final class ImportGitHubFollowsCommand extends Command
{
    public function handle(CreateUserGitHubFollows $createUserGitHubFollows): int
    {
        $imported = 0;
        $failed = 0;

        foreach (User::query()->whereNull('github_follows_imported_at')->lazyById() as $user) {
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

    private function failed(User $user, RequestException|ConnectionException $exception): void
    {
        report($exception);

        $this->components->warn("Could not import the GitHub follows of {$user->mention()}.");
    }
}
