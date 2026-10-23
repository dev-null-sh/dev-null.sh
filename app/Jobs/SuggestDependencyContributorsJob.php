<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CreateStackSuggestions;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

#[Backoff([10, 60])]
#[Timeout(120)]
#[Tries(3)]
final class SuggestDependencyContributorsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $user)
    {
        //
    }

    public function handle(CreateStackSuggestions $createStackSuggestions): void
    {
        $createStackSuggestions->handle($this->user);
    }
}
