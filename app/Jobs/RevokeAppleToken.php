<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\AppleTokens;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;

/**
 * Revokes at Apple the refresh token of an Apple ID that was unlinked, or whose account was deleted. The user is gone by
 * then, or no longer has the token, so the job carries it, encrypted on the queue, and tries again for a while if Apple
 * can't be reached.
 */
#[Backoff([60, 600, 3600])]
#[Tries(4)]
final class RevokeAppleToken implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $refreshToken)
    {
        //
    }

    public function handle(AppleTokens $appleTokens): void
    {
        $appleTokens->revoke($this->refreshToken);
    }
}
