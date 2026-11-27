<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Queries\ChangelogQuery;

/**
 * Writes the email the admins send to everyone about what shipped.
 *
 * @phpstan-import-type ChangelogCommit from ChangelogQuery
 */
interface EmailDraftProvider
{
    /**
     * A draft about the commits that landed on production in the last days.
     *
     * @param  list<ChangelogCommit>  $commits
     * @return array{subject: string, body: string}
     */
    public function draft(int $days, array $commits): array;
}
