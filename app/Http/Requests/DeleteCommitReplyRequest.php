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
     * Replies of deleted commits stay deletable, as they stay in a thread under its tombstone.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $reply = $this->route('reply');
        assert($reply instanceof Commit);

        if ($reply->trashed()) {
            return Response::denyAsNotFound();
        }

        return $reply->user_id === $user->id ? Response::allow() : Response::deny();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
