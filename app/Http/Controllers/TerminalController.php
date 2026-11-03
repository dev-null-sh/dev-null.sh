<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\SyncGitHubSshKeysJob;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

final readonly class TerminalController
{
    /**
     * Also syncs the user's SSH keys from GitHub, at most once a minute: the `devnull commit` script can't tell whose keys
     * it sends, so opening this page is how a key added on GitHub after signing in reaches /dev/null.
     */
    public function show(#[CurrentUser] User $user): Response
    {
        RateLimiter::attempt("terminal-ssh-keys-sync:{$user->id}", 1, fn () => dispatch(new SyncGitHubSshKeysJob($user)));

        return Inertia::render('terminal/show', ['url' => url('/')]);
    }
}
