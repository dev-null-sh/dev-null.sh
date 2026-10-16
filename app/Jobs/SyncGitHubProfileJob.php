<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\UpdateUserProfileFromGitHub;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\UniqueFor;

#[DeleteWhenMissingModels]
#[UniqueFor(3600)]
final class SyncGitHubProfileJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public User $user)
    {
        //
    }

    public function uniqueId(): string
    {
        return $this->user->id;
    }

    public function handle(UpdateUserProfileFromGitHub $updateUserProfileFromGitHub): void
    {
        $updateUserProfileFromGitHub->handle($this->user);
    }
}
