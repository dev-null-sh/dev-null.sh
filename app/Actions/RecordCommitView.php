<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use Illuminate\Contracts\Session\Session;

final readonly class RecordCommitView
{
    public function handle(Commit $commit, Session $session): void
    {
        $key = "viewed_commits.{$commit->id}";

        if ($session->has($key)) {
            return;
        }

        $session->put($key, true);

        Commit::withoutTimestamps(fn () => $commit->increment('views_count'));
    }
}
