<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\UpdateUserSshKeys;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

#[Signature('ssh-keys:sync')]
#[Description('Mirror the SSH keys of all users from GitHub (used by the `devnull commit` script)')]
final class SyncSshKeysCommand extends Command
{
    public function handle(UpdateUserSshKeys $updateUserSshKeys): int
    {
        $synced = 0;
        $failed = 0;

        foreach (User::query()->lazyById() as $user) {
            try {
                $updateUserSshKeys->handle($user);

                $synced++;
            } catch (RequestException $exception) {
                if (in_array($exception->response->status(), [403, 429], true)) {
                    $this->components->error("GitHub rate limit reached after {$synced} users. Run the command again later.");

                    return self::FAILURE;
                }

                $this->failed($user, $exception);

                $failed++;
            } catch (ConnectionException $exception) {
                $this->failed($user, $exception);

                $failed++;
            }
        }

        $this->components->info("Synced the SSH keys of {$synced} users from GitHub.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function failed(User $user, RequestException|ConnectionException $exception): void
    {
        report($exception);

        $this->components->warn("Could not sync the SSH keys of {$user->mention()}.");
    }
}
