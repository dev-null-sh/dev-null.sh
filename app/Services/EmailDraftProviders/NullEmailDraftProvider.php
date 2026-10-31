<?php

declare(strict_types=1);

namespace App\Services\EmailDraftProviders;

use App\Contracts\Services\EmailDraftProvider;

/**
 * Writes nothing, so developing and testing never reach a third party.
 */
final readonly class NullEmailDraftProvider implements EmailDraftProvider
{
    public function draft(int $days, array $pullRequests): array
    {
        return ['subject' => '', 'body' => ''];
    }
}
