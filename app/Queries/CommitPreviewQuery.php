<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;

final readonly class CommitPreviewQuery
{
    public function get(Commit $commit, User $author, ?User $viewer): Commit
    {
        return Commit::query()
            ->with(['images', 'linkPreview'])
            ->withPollFor($viewer)
            ->findOrFail($commit->id)
            ->setRelation('user', $author);
    }
}
