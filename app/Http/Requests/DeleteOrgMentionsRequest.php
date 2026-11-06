<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrgRole;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\OrgMentionsQuery;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteOrgMentionsRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $org = $this->route('org');
        assert($org instanceof Conversation);

        return $user->remotes_enabled && $org->roleOf($user) instanceof OrgRole ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'message_ids' => ['sometimes', 'array', 'min:1', 'max:'.OrgMentionsQuery::LIMIT],
            'message_ids.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * The mentions to clear, or none to clear them all.
     *
     * @return list<int>|null
     */
    public function messageIds(): ?array
    {
        if (! $this->has('message_ids')) {
            return null;
        }

        /** @var list<int> $ids */
        $ids = $this->array('message_ids');

        return $ids;
    }
}
