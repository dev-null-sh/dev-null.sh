<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\search;

#[Signature('local:sign-in {username? : The developer to sign in as, searched for when left out}')]
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
            $username = search(
                label: 'Which developer do you want to sign in as?',
                options: fn (string $value): array => User::query()
                    ->whereLike('username', "%{$value}%")
                    ->orderBy('username')
                    ->limit(10)
                    ->get(['username'])
                    ->map(fn (User $user): string => $user->username)
                    ->all(),
            );
        }

        $username = mb_ltrim((string) $username, '@');

        $user = User::query()->where('username', $username)->first();

        if (! $user instanceof User) {
            $this->components->error("There is no developer @{$username}.");

            return self::FAILURE;
        }

        $this->components->info("Open this link to sign in as @{$user->username}:");

        $this->line('  '.route('local-session.store', $user));

        return self::SUCCESS;
    }
}
