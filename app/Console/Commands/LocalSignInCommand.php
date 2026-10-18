<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\suggest;

#[Signature('local:sign-in {username? : The developer to sign in as, or part of their username}')]
#[Description('Print the link that signs you in locally as any developer')]
final class LocalSignInCommand extends Command
{
    public function handle(): int
    {
        if (! config('app.auto_login')) {
            $this->components->error('Signing in as any developer needs AUTO_LOGIN=true in the local environment.');

            return self::FAILURE;
        }

        $username = $this->argument('username');

        if (! is_string($username)) {
            $username = suggest(
                label: 'Which developer do you want to sign in as?',
                options: User::query()
                    ->orderBy('username')
                    ->get(['username'])
                    ->map(fn (User $user): string => $user->username)
                    ->all(),
                placeholder: 'Type part of a username, then press enter for the first match',
                hint: 'Use the arrow keys to pick another match.',
            );
        }

        $username = mb_ltrim($username, '@');

        $user = User::query()->where('username', $username)->first()
            ?? User::query()->whereLike('username', "%{$username}%")->orderBy('username')->first();

        if (! $user instanceof User) {
            $this->components->error("There is no developer matching @{$username}.");

            return self::FAILURE;
        }

        $this->components->info("Open this link to sign in as @{$user->username}:");

        $this->line('  '.route('local-session.store', $user));

        return self::SUCCESS;
    }
}
