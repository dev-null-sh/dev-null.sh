<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteCommitReplyRequest extends FormRequest
{
    /**
     * Replies and quotes of deleted commits stay deletable under their tombstone.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        $reply = $this->route('reply');
        assert($reply instanceof Commit);

        if ($reply->trashed() || ($reply->parent_id !== $commit->id && $reply->quoted_commit_id !== $commit->id)) {
            return Response::denyAsNotFound();
        }

        return $reply->isOwnedBy($user) ? Response::allow() : Response::deny();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
