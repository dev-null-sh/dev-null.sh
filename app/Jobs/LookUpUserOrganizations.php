<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\UpdateMentionedOrganizations;
use App\Models\User;
use App\Services\Mentions;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\MaxExceptions;

/**
 * Looks the organizations a user's bio and company mention up, so their profile can link to them. Runs again once the
 * rate limit resets when it cut the lookups short, and gives up after three failures, or a day after it was queued.
 */
#[Backoff([60, 600])]
#[MaxExceptions(3)]
#[DeleteWhenMissingModels]
final class LookUpUserOrganizations implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly User $user)
    {
        //
    }

    /**
     * Queues the lookup when the user's bio or company mentions anyone, and says whether it did.
     */
    public static function dispatchFor(User $user): bool
    {
        if (self::handles($user) === []) {
            return false;
        }

        dispatch(new self($user));

        return true;
    }

    public function retryUntil(): CarbonImmutable
    {
        return CarbonImmutable::now()->addDay();
    }

    public function handle(UpdateMentionedOrganizations $action): void
    {
        $retryAt = $action->handle(self::handles($this->user));

        if ($retryAt instanceof CarbonImmutable) {
            $this->release($retryAt);
        }
    }

    /**
     * @return list<string>
     */
    private static function handles(User $user): array
    {
        return resolve(Mentions::class)->handles("{$user->bio}\n{$user->company}");
    }
}
