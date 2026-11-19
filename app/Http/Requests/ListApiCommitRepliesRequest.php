<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ListApiCommitRepliesRequest extends FormRequest
{
    /**
     * Deleted commits still list their replies, as they stay in a thread as tombstones.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return $commit->isVisibleTo($user) ? Response::allow() : Response::denyAsNotFound();
    }

    public function exceptedReplyId(): ?string
    {
        return $this->string('except')->value() ?: null;
    }

    /**
     * Newest first continues the next_cursor of a reply preview, as on the web. Except leaves out the reply that
     * continues the author's thread, which the web shows on its own.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'newest' => ['sometimes', 'boolean'],
            'except' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
