<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ListCommitRepliesRequest extends FormRequest
{
    /**
     * Deleted commits still list their replies, as they stay in a thread as tombstones. Guests only see approved ones.
     */
    public function authorize(): Response
    {
        $user = $this->user();

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return $commit->isVisibleTo($user) ? Response::allow() : Response::denyAsNotFound();
    }

    public function exceptedReplyId(): ?string
    {
        return $this->string('except')->value() ?: null;
    }

    /**
     * The replies a thread heard changed, to read again on their own rather than with everything loaded around them.
     *
     * @return list<string>|null
     */
    public function replyIds(): ?array
    {
        /** @var list<string>|null $ids */
        $ids = $this->validated('ids');

        return $ids;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'newest' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'between:1,50'],
            'except' => ['sometimes', 'nullable', 'uuid'],
            'ids' => ['sometimes', 'array', 'list', 'max:50'],
            'ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
