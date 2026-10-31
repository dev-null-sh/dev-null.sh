<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Queries\ChangelogQuery;

/**
 * Writes the email the admins send to everyone about what shipped.
 *
 * @phpstan-import-type ChangelogPullRequest from ChangelogQuery
 */
interface EmailDraftProvider
{
    /**
     * A draft about the pull requests merged into production in the last days.
     *
     * @param  list<ChangelogPullRequest>  $pullRequests
     * @return array{subject: string, body: string}
     */
    public function draft(int $days, array $pullRequests): array;
}
