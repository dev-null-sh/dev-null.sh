<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\BookmarkCollection;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateBookmarkCollectionCommitRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $collection = $this->route('bookmarkCollection');
        assert($collection instanceof BookmarkCollection);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        if (! $collection->isOwnedBy($user) || ! $commit->isVisibleTo($user)) {
            return Response::denyAsNotFound();
        }

        return $commit->bookmarks()->where('user_id', $user->id)->exists()
            ? Response::allow()
            : Response::deny(__('Save this commit before adding it to a collection.'));
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
