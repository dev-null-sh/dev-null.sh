<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CreateTomFriendship;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tom:befriend')]
#[Description('Make Tom and every existing user follow each other, as new users do when they sign up')]
final class BefriendTomCommand extends Command
{
    public function handle(CreateTomFriendship $createTomFriendship): int
    {
        $users = User::query()->where('github_id', '!=', CreateTomFriendship::GITHUB_ID)->lazyById();

        foreach ($users as $user) {
            $createTomFriendship->handle($user);
        }

        $this->components->info("Tom is everyone's friend.");

        return self::SUCCESS;
    }
}
