<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitEmbedQuery;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowCommitEmbedLinkRequest extends FormRequest
{
    /**
     * A commit that can't be embedded, unapproved or from an author who turned embeds off, or one the member can't see,
     * because of a block, looks like it doesn't exist.
     */
    public function authorize(): Response
    {
        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        $user = $this->user();
        $isVisible = $user instanceof User && $commit->isVisibleTo($user);

        return $isVisible && CommitEmbedQuery::tombstone($commit) === null
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Every setting falls back to its default instead of failing, like the card does.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
