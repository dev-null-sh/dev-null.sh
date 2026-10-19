<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Finds the commits the user can see from a hash (as shown on devnull: the last 7 characters of its
 * ID), an ID, or a link.
 */
final readonly class CommitByReferenceQuery
{
    private const string Uuid = '/(?<![a-z0-9-])[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}(?![a-z0-9-])/i';

    private const string Hash = '/^[0-9a-f]{7,12}$/i';

    /**
     * At most two commits are returned, enough to tell an ambiguous hash apart. Null means the reference is not a hash, an ID, or a link.
     *
     * @return Collection<int, Commit>|null
     */
    public function get(User $user, string $reference): ?Collection
    {
        $commits = Commit::query()->visibleTo($user);

        if (preg_match(self::Uuid, $reference, $matches) === 1) {
            $commits->whereKey(mb_strtolower($matches[0]));
        } elseif (preg_match(self::Hash, $reference) === 1) {
            $commits->whereLike('id', '%'.mb_strtolower($reference));
        } else {
            return null;
        }

        return $commits->limit(2)->get();
    }
}
