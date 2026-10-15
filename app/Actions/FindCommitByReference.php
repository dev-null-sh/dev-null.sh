<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Finds a commit the user can see from its hash (as shown on devnull: the last 7 characters of its
 * ID), its ID, or its link.
 */
final readonly class FindCommitByReference
{
    private const string Uuid = '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i';

    private const string Hash = '/^[0-9a-f]{7,12}$/i';

    public function handle(User $user, string $reference): Commit
    {
        $commits = Commit::query()->visibleTo($user);

        if (preg_match(self::Uuid, $reference, $matches) === 1) {
            $commits->whereKey(mb_strtolower($matches[0]));
        } elseif (preg_match(self::Hash, $reference) === 1) {
            $commits->whereLike('id', '%'.mb_strtolower($reference));
        } else {
            throw ValidationException::withMessages(['reply_to' => __(':reference is not a commit hash or link.', ['reference' => $reference])]);
        }

        $matching = $commits->limit(2)->get();

        if ($matching->count() > 1) {
            throw ValidationException::withMessages(['reply_to' => __(':reference matches several commits: use the link of the commit instead.', ['reference' => $reference])]);
        }

        return $matching->first() ?? throw ValidationException::withMessages(['reply_to' => __('There is no commit :reference to reply to.', ['reference' => $reference])]);
    }
}
