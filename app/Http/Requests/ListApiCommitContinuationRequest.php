<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Str;

final class ListApiCommitContinuationRequest extends FormRequest
{
    private ?Commit $continued = null;

    /**
     * The cursor of a page after the first continues the thread from the last commit of the page before, so the user
     * must still see that commit too.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return $commit->isVisibleTo($user) && $this->continuedCommit()?->isVisibleTo($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * The commit whose next commits this page lists: the commit itself, or the one the cursor continues from.
     */
    public function continuedCommit(): ?Commit
    {
        if ($this->continued instanceof Commit) {
            return $this->continued;
        }

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        $cursor = $this->query('cursor');
        $id = is_string($cursor) ? Cursor::fromEncoded($cursor)?->toArray()['id'] ?? null : null;

        if ($id === null) {
            return $this->continued = $commit;
        }

        return $this->continued = is_string($id) && Str::isUuid($id) ? Commit::query()->find($id) : null;
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
